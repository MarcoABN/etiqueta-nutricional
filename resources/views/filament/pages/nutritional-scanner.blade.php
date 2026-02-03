<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&display=swap');
        
        /* === RESET COMPLETO FILAMENT === */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-logo, 
        .fi-sidebar, .fi-footer { display: none !important; }
        
        .fi-main-ctn { 
            padding: 0 !important; 
            margin: 0 !important; 
            max-width: 100% !important; 
        }
        
        .fi-page { 
            padding: 0 !important; 
            height: 100vh; 
            height: 100dvh; /* Mobile viewport */
            overflow: hidden; 
            background: #000;
            font-family: 'JetBrains Mono', monospace;
        }

        /* === ESCONDE COMPLETAMENTE O FILEPOND === */
        .filepond--root,
        .filepond--panel-root,
        .filepond--drop-label,
        .filepond--credits { 
            display: none !important; 
            opacity: 0 !important;
            pointer-events: none !important;
        }

        /* === LAYOUT PRINCIPAL === */
        .app-container {
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            background: #000;
            position: relative;
        }

        /* === MODO SCANNER === */
        #scanner-view {
            position: relative;
            flex: 1;
            background: #000;
            overflow: hidden;
        }

        #reader {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Remove bordas do html5-qrcode */
        #reader video {
            object-fit: cover;
            border-radius: 0 !important;
        }

        /* Overlay escuro sutil */
        .scanner-overlay {
            position: absolute;
            inset: 0;
            background: radial-gradient(
                ellipse at center,
                transparent 30%,
                rgba(0,0,0,0.4) 70%,
                rgba(0,0,0,0.7) 100%
            );
            pointer-events: none;
        }

        /* Mira minimalista */
        .scan-frame {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: min(85vw, 320px);
            height: 180px;
            border: 2px solid rgba(34, 197, 94, 0.6);
            border-radius: 12px;
            pointer-events: none;
        }

        .scan-corner {
            position: absolute;
            width: 24px;
            height: 24px;
            border-color: #22c55e;
        }

        .scan-corner.tl { top: -2px; left: -2px; border-top: 3px solid; border-left: 3px solid; }
        .scan-corner.tr { top: -2px; right: -2px; border-top: 3px solid; border-right: 3px solid; }
        .scan-corner.bl { bottom: -2px; left: -2px; border-bottom: 3px solid; border-left: 3px solid; }
        .scan-corner.br { bottom: -2px; right: -2px; border-bottom: 3px solid; border-right: 3px solid; }

        /* Linha de scan animada */
        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #22c55e, transparent);
            animation: scan 2s ease-in-out infinite;
            box-shadow: 0 0 8px #22c55e;
        }

        @keyframes scan {
            0%, 100% { top: 0; opacity: 0; }
            50% { top: 50%; opacity: 1; }
        }

        /* Indicador de status */
        .scan-status {
            position: absolute;
            bottom: 120px;
            left: 50%;
            transform: translateX(-50%);
            color: #22c55e;
            font-size: 0.813rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            text-shadow: 0 2px 8px rgba(0,0,0,0.8);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }

        /* === MODO FOTO === */
        #photo-view {
            display: flex;
            flex-direction: column;
            flex: 1;
            background: #0a0a0a;
        }

        /* Header produto */
        .product-header {
            background: linear-gradient(180deg, #1a1a1a 0%, #0f0f0f 100%);
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #2a2a2a;
        }

        .product-name {
            color: #fff;
            font-size: 1.125rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 0.375rem;
        }

        .product-ean {
            color: #22c55e;
            font-size: 0.813rem;
            font-weight: 600;
            letter-spacing: 0.1em;
        }

        /* Área da câmera */
        .camera-area {
            flex: 1;
            position: relative;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Preview da imagem quando carregada */
        .camera-area img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* Placeholder da câmera */
        .camera-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
            pointer-events: none;
        }

        .camera-icon {
            width: 72px;
            height: 72px;
            color: #404040;
            transition: all 0.3s ease;
        }

        .camera-hint {
            color: #606060;
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* Input invisível mas clicável */
        .camera-trigger {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }

        /* Barra de ações inferior */
        .action-bar {
            background: #000;
            padding: 1.5rem 1.25rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid #1a1a1a;
        }

        /* Botões */
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0.75rem;
            border-radius: 50%;
        }

        .btn:active {
            transform: scale(0.92);
        }

        .btn-close {
            color: #999;
        }

        .btn-close:hover {
            background: #1a1a1a;
            color: #fff;
        }

        .btn-save {
            color: #22c55e;
        }

        .btn-save:hover {
            background: rgba(34, 197, 94, 0.1);
        }

        /* Botão shutter central */
        .shutter-container {
            position: relative;
        }

        .shutter-btn {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 4px solid #2a2a2a;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }

        .shutter-btn:active {
            transform: scale(0.9);
            background: #f5f5f5;
        }

        .shutter-inner {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #22c55e;
            transition: all 0.15s ease;
        }

        .shutter-btn:active .shutter-inner {
            transform: scale(0.85);
        }

        /* Badge de status */
        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid #22c55e;
            color: #22c55e;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.688rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        /* Utilitários */
        .hidden { display: none !important; }
        
        /* Ícones SVG */
        svg {
            width: 100%;
            height: 100%;
        }
    </style>

    <div class="app-container">
        
        {{-- ============================================ --}}
        {{-- MODO 1: SCANNER DE CÓDIGO DE BARRAS          --}}
        {{-- ============================================ --}}
        <div id="scanner-view" class="{{ $foundProduct ? 'hidden' : '' }}">
            
            <div id="reader"></div>
            
            <div class="scanner-overlay"></div>
            
            <div class="scan-frame">
                <div class="scan-corner tl"></div>
                <div class="scan-corner tr"></div>
                <div class="scan-corner bl"></div>
                <div class="scan-corner br"></div>
                <div class="scan-line"></div>
            </div>
            
            <div class="scan-status">
                Posicione o código de barras
            </div>
            
        </div>

        {{-- ============================================ --}}
        {{-- MODO 2: CAPTURA DE FOTO                      --}}
        {{-- ============================================ --}}
        <div id="photo-view" class="{{ $foundProduct ? '' : 'hidden' }}">
            
            {{-- Header com info do produto --}}
            <div class="product-header">
                <div class="product-name">{{ $foundProduct?->product_name ?? '' }}</div>
                <div class="product-ean">EAN {{ $scannedCode }}</div>
            </div>

            {{-- Área da câmera --}}
            <div class="camera-area">
                
                {{-- Input invisível do Filament (captura real) --}}
                <div class="camera-trigger">
                    {{ $this->form }}
                </div>

                {{-- Placeholder visual --}}
                <div class="camera-placeholder">
                    <svg class="camera-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                              d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                              d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <div class="camera-hint">Toque para capturar</div>
                </div>

            </div>

            {{-- Barra de ações --}}
            <div class="action-bar">
                
                <button wire:click="resetScanner" type="button" class="btn btn-close">
                    <svg style="width: 32px; height: 32px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="shutter-container">
                    <div class="shutter-btn">
                        <div class="shutter-inner"></div>
                    </div>
                </div>

                <button wire:click="save" type="button" class="btn btn-save">
                    <svg style="width: 36px; height: 36px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
                
            </div>

            @if($foundProduct)
                <div class="status-badge">Detectado</div>
            @endif

        </div>

    </div>

    {{-- ============================================ --}}
    {{-- JAVASCRIPT: CONTROLE DO SCANNER E CÂMERA    --}}
    {{-- ============================================ --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;
            let currentCameraId = null;

            /**
             * Seleciona a melhor câmera traseira disponível
             * Prioriza câmeras com keywords: back, rear, environment, main
             */
            async function selectBestCamera() {
                try {
                    const devices = await Html5Qrcode.getCameras();
                    
                    if (!devices || devices.length === 0) {
                        console.warn('Nenhuma câmera detectada');
                        return { facingMode: "environment" };
                    }

                    // Prioridade de busca por keywords (ordem de preferência)
                    const priorities = [
                        /back.*main/i,      // "back main camera" (câmera principal traseira)
                        /rear.*main/i,      // "rear main camera"
                        /back.*wide/i,      // "back wide" (grande angular traseira)
                        /back/i,            // qualquer "back"
                        /rear/i,            // qualquer "rear"
                        /environment/i,     // "environment"
                        /camera.*[23]/i     // "camera 2", "camera 3" (comuns em Android)
                    ];

                    // Tenta encontrar a melhor câmera por prioridade
                    for (const pattern of priorities) {
                        const camera = devices.find(d => pattern.test(d.label));
                        if (camera) {
                            console.log('✓ Câmera selecionada:', camera.label);
                            currentCameraId = camera.id;
                            return { deviceId: { exact: camera.id } };
                        }
                    }

                    // Fallback: última câmera da lista (geralmente traseira em mobiles)
                    const lastCamera = devices[devices.length - 1];
                    console.log('→ Usando última câmera disponível:', lastCamera.label);
                    currentCameraId = lastCamera.id;
                    return { deviceId: { exact: lastCamera.id } };

                } catch (err) {
                    console.warn('Erro ao selecionar câmera, usando padrão:', err);
                    return { facingMode: "environment" };
                }
            }

            /**
             * Inicia o scanner com a melhor câmera disponível
             */
            async function startScanner() {
                if (@json($foundProduct)) {
                    stopScanner();
                    return;
                }

                try {
                    const cameraConfig = await selectBestCamera();
                    
                    const scannerConfig = { 
                        fps: 10, 
                        qrbox: { width: 280, height: 160 },
                        aspectRatio: 1.0,
                        disableFlip: false
                    };
                    
                    html5QrCode = new Html5Qrcode("reader");
                    
                    await html5QrCode.start(
                        cameraConfig,
                        scannerConfig,
                        (decodedText) => {
                            stopScanner();
                            @this.handleBarcodeScan(decodedText);
                        },
                        (errorMessage) => {
                            // Silencia erros de scan em andamento
                        }
                    );
                    
                } catch (err) {
                    console.error('Erro ao iniciar scanner:', err);
                    
                    if (err.toString().includes('permission')) {
                        alert('⚠️ Permissão de câmera negada. Ative nas configurações do navegador.');
                    } else if (err.toString().includes('NotFoundError')) {
                        alert('⚠️ Nenhuma câmera encontrada no dispositivo.');
                    } else {
                        alert('⚠️ Erro ao acessar a câmera. Verifique as permissões.');
                    }
                }
            }

            /**
             * Para o scanner e limpa recursos
             */
            function stopScanner() {
                if (html5QrCode) {
                    html5QrCode.stop()
                        .then(() => html5QrCode.clear())
                        .catch(err => console.warn('Erro ao parar scanner:', err));
                }
            }

            // ===== INICIALIZAÇÃO =====
            startScanner();

            // ===== EVENTOS LIVEWIRE =====
            Livewire.on('reset-scanner', () => {
                setTimeout(startScanner, 300);
            });
            
            Livewire.on('reset-scanner-error', () => {
                setTimeout(startScanner, 1200);
            });

            // ===== LIMPEZA AO SAIR =====
            window.addEventListener('beforeunload', stopScanner);
        });
    </script>
</x-filament-panels::page>