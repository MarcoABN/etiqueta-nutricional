<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
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
            height: 100dvh;
            overflow: hidden; 
            background: #000;
            font-family: 'Inter', -apple-system, system-ui, sans-serif;
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

        /* === VARIÁVEIS === */
        :root {
            --primary: #22c55e;
            --primary-dark: #16a34a;
        }

        /* === LAYOUT PRINCIPAL === */
        .app-container {
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            background: #000;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        /* === MODO SCANNER === */
        #scanner-view {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #000;
            overflow: hidden;
        }

        #reader {
            position: absolute;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
        }

        /* Força vídeo ocupar tela inteira */
        #reader video {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 0 !important;
        }

        /* Esconde elementos do html5-qrcode */
        #reader__dashboard_section,
        #reader__dashboard_section_csr,
        #reader__scan_region {
            display: none !important;
        }

        /* Overlay escuro sutil */
        .scanner-overlay {
            position: absolute;
            inset: 0;
            background: radial-gradient(
                ellipse at center,
                transparent 25%,
                rgba(0,0,0,0.3) 60%,
                rgba(0,0,0,0.6) 100%
            );
            pointer-events: none;
            z-index: 1;
        }

        /* Mira minimalista - ÚNICA */
        .scan-frame {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: min(80vw, 300px);
            height: 160px;
            pointer-events: none;
            z-index: 2;
        }

        .scan-corner {
            position: absolute;
            width: 20px;
            height: 20px;
            border-color: var(--primary);
        }

        .scan-corner.tl { top: 0; left: 0; border-top: 3px solid; border-left: 3px solid; }
        .scan-corner.tr { top: 0; right: 0; border-top: 3px solid; border-right: 3px solid; }
        .scan-corner.bl { bottom: 0; left: 0; border-bottom: 3px solid; border-left: 3px solid; }
        .scan-corner.br { bottom: 0; right: 0; border-bottom: 3px solid; border-right: 3px solid; }

        /* Linha de scan */
        .scan-line {
            position: absolute;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
            animation: scan 2s ease-in-out infinite;
            box-shadow: 0 0 10px var(--primary);
        }

        @keyframes scan {
            0%, 100% { top: 0; opacity: 0; }
            50% { top: calc(100% - 2px); opacity: 1; }
        }

        /* Status text */
        .scan-status {
            position: absolute;
            bottom: 140px;
            left: 50%;
            transform: translateX(-50%);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 500;
            text-align: center;
            text-shadow: 0 2px 10px rgba(0,0,0,0.8);
            z-index: 2;
            white-space: nowrap;
        }

        /* Botão de trocar câmera - discreto */
        .camera-switch-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 44px;
            height: 44px;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 10;
        }

        .camera-switch-btn:active {
            transform: scale(0.9);
            background: rgba(0,0,0,0.7);
        }

        .camera-switch-btn svg {
            width: 22px;
            height: 22px;
            color: #fff;
        }

        /* === MODO FOTO === */
        #photo-view {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            background: #000;
        }

        /* Header produto - compacto */
        .product-header {
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(10px);
            padding: 0.875rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }

        .product-name {
            color: #fff;
            font-size: 0.938rem;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 0.25rem;
        }

        .product-ean {
            color: var(--primary);
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.05em;
        }

        /* Área da câmera - ocupa todo espaço disponível */
        .camera-area {
            flex: 1;
            position: relative;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            min-height: 0;
        }

        /* Preview da imagem */
        .camera-area img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* Placeholder */
        .camera-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            pointer-events: none;
        }

        .camera-icon {
            width: 64px;
            height: 64px;
            color: #333;
        }

        .camera-hint {
            color: #666;
            font-size: 0.813rem;
            font-weight: 500;
        }

        /* Input invisível */
        .camera-trigger {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }

        /* Barra inferior */
        .action-bar {
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(10px);
            padding: 1.25rem 1rem;
            padding-bottom: max(1.25rem, env(safe-area-inset-bottom));
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid rgba(255,255,255,0.05);
            flex-shrink: 0;
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
            padding: 0.625rem;
            border-radius: 50%;
            -webkit-tap-highlight-color: transparent;
        }

        .btn:active {
            transform: scale(0.88);
        }

        .btn-close {
            color: #999;
        }

        .btn-close:active {
            background: rgba(255,255,255,0.05);
        }

        .btn-save {
            color: var(--primary);
        }

        .btn-save:active {
            background: rgba(34, 197, 94, 0.1);
        }

        /* Shutter */
        .shutter-btn {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            border: 3px solid #222;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            -webkit-tap-highlight-color: transparent;
        }

        .shutter-btn:active {
            transform: scale(0.88);
            background: #f0f0f0;
        }

        .shutter-inner {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: var(--primary);
        }

        .shutter-btn:active .shutter-inner {
            transform: scale(0.82);
        }

        /* Badge */
        .status-badge {
            position: absolute;
            top: 0.875rem;
            right: 0.875rem;
            background: rgba(34, 197, 94, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 0.313rem 0.625rem;
            border-radius: 5px;
            font-size: 0.688rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            z-index: 5;
        }

        /* Utilitários */
        .hidden { display: none !important; }
        
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
            
            {{-- Vídeo da câmera ocupa TODA a tela --}}
            <div id="reader"></div>
            
            {{-- Overlay escuro --}}
            <div class="scanner-overlay"></div>
            
            {{-- Frame único de scan --}}
            <div class="scan-frame">
                <div class="scan-corner tl"></div>
                <div class="scan-corner tr"></div>
                <div class="scan-corner bl"></div>
                <div class="scan-corner br"></div>
                <div class="scan-line"></div>
            </div>
            
            {{-- Texto de instrução --}}
            <div class="scan-status">Posicione o código de barras</div>
            
            {{-- Botão de trocar câmera --}}
            <button id="switch-camera-btn" class="camera-switch-btn" title="Trocar câmera">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>
            
        </div>

        {{-- ============================================ --}}
        {{-- MODO 2: CAPTURA DE FOTO                      --}}
        {{-- ============================================ --}}
        <div id="photo-view" class="{{ $foundProduct ? '' : 'hidden' }}">
            
            {{-- Header compacto --}}
            <div class="product-header">
                <div class="product-name">{{ $foundProduct?->product_name ?? '' }}</div>
                <div class="product-ean">EAN {{ $scannedCode }}</div>
            </div>

            {{-- Área de captura --}}
            <div class="camera-area">
                
                {{-- Input do Filament (invisível) --}}
                <div class="camera-trigger">
                    {{ $this->form }}
                </div>

                {{-- Placeholder --}}
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
                    <svg style="width: 28px; height: 28px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="shutter-btn">
                    <div class="shutter-inner"></div>
                </div>

                <button wire:click="save" type="button" class="btn btn-save">
                    <svg style="width: 32px; height: 32px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
            let availableCameras = [];
            let currentCameraIndex = 0;

            /**
             * Detecta todas as câmeras disponíveis
             */
            async function detectCameras() {
                try {
                    const devices = await Html5Qrcode.getCameras();
                    if (!devices || devices.length === 0) {
                        console.warn('Nenhuma câmera detectada');
                        return [];
                    }
                    
                    availableCameras = devices;
                    console.log('📷 Câmeras detectadas:', devices.map((d, i) => `${i}: ${d.label}`));
                    return devices;
                    
                } catch (err) {
                    console.error('Erro ao detectar câmeras:', err);
                    return [];
                }
            }

            /**
             * Seleciona o índice da melhor câmera traseira
             * Retorna o índice da câmera na lista
             */
            function selectBestCameraIndex(cameras) {
                if (!cameras || cameras.length === 0) return 0;
                if (cameras.length === 1) return 0;

                // Estratégia de priorização
                const priorities = [
                    // Samsung S24 Ultra tem: "camera2 0, facing back" para ultra-wide
                    // "camera2 1, facing back" para wide (melhor para barcode/macro)
                    // "camera2 2, facing back" para telephoto
                    /back.*[1-3]/i,      // "back 1", "back 2" - geralmente wide/ultra-wide
                    /rear.*[1-3]/i,      // "rear 1", "rear 2"
                    /camera.*[1-3].*back/i, // "camera 1 back", "camera2 1, facing back"
                    /wide.*back/i,       // "wide back"
                    /back.*wide/i,       // "back wide"
                    /ultra.*back/i,      // "ultra back" (pode ser melhor para macro)
                    /back(?!.*front)/i,  // qualquer "back" que não tenha "front"
                    /rear(?!.*front)/i,  // qualquer "rear" que não tenha "front"
                    /environment/i       // fallback "environment"
                ];

                // Tenta encontrar por prioridade
                for (const pattern of priorities) {
                    const index = cameras.findIndex(d => pattern.test(d.label));
                    if (index !== -1) {
                        console.log(`✓ Câmera selecionada [${index}]: ${cameras[index].label}`);
                        return index;
                    }
                }

                // Fallback: penúltima câmera (geralmente a traseira principal)
                // Última costuma ser telephoto ou zoom
                const fallbackIndex = Math.max(0, cameras.length - 2);
                console.log(`→ Usando fallback [${fallbackIndex}]: ${cameras[fallbackIndex].label}`);
                return fallbackIndex;
            }

            /**
             * Inicia o scanner com a câmera especificada
             */
            async function startScanner(cameraIndex = null) {
                if (@json($foundProduct)) {
                    stopScanner();
                    return;
                }

                try {
                    // Detecta câmeras se ainda não detectou
                    if (availableCameras.length === 0) {
                        await detectCameras();
                    }

                    // Define qual câmera usar
                    if (cameraIndex !== null) {
                        currentCameraIndex = cameraIndex;
                    } else if (currentCameraIndex === 0 && availableCameras.length > 0) {
                        // Primeira vez: seleciona a melhor automaticamente
                        currentCameraIndex = selectBestCameraIndex(availableCameras);
                    }

                    // Configuração da câmera
                    let cameraConfig;
                    if (availableCameras.length > 0 && availableCameras[currentCameraIndex]) {
                        cameraConfig = { 
                            deviceId: { exact: availableCameras[currentCameraIndex].id }
                        };
                    } else {
                        // Fallback para facingMode
                        cameraConfig = { facingMode: "environment" };
                    }

                    // Configuração do scanner
                    const scannerConfig = { 
                        fps: 10,
                        qrbox: { width: 260, height: 140 },
                        aspectRatio: 9/16, // Otimizado para mobile
                        disableFlip: false,
                        videoConstraints: {
                            facingMode: "environment",
                            aspectRatio: 9/16,
                            // Força resolução mais alta para melhor leitura
                            width: { ideal: 1920 },
                            height: { ideal: 1080 }
                        }
                    };

                    // Cria e inicia scanner
                    html5QrCode = new Html5Qrcode("reader");
                    
                    await html5QrCode.start(
                        cameraConfig,
                        scannerConfig,
                        (decodedText) => {
                            // Sucesso na leitura
                            stopScanner();
                            @this.handleBarcodeScan(decodedText);
                        },
                        (errorMessage) => {
                            // Silencia erros de scan em andamento
                        }
                    );

                    // Força vídeo em fullscreen após iniciar
                    setTimeout(() => {
                        const video = document.querySelector('#reader video');
                        if (video) {
                            video.style.cssText = `
                                position: absolute !important;
                                top: 0 !important;
                                left: 0 !important;
                                width: 100% !important;
                                height: 100% !important;
                                object-fit: cover !important;
                            `;
                        }
                    }, 100);
                    
                } catch (err) {
                    console.error('Erro ao iniciar scanner:', err);
                    
                    if (err.toString().includes('permission')) {
                        alert('⚠️ Permissão negada. Ative a câmera nas configurações do navegador.');
                    } else if (err.toString().includes('NotFoundError')) {
                        alert('⚠️ Nenhuma câmera encontrada.');
                    } else {
                        console.error('Detalhes do erro:', err);
                    }
                }
            }

            /**
             * Para o scanner
             */
            function stopScanner() {
                if (html5QrCode) {
                    html5QrCode.stop()
                        .then(() => {
                            html5QrCode.clear();
                            html5QrCode = null;
                        })
                        .catch(err => console.warn('Erro ao parar:', err));
                }
            }

            /**
             * Troca para próxima câmera disponível
             */
            async function switchCamera() {
                if (availableCameras.length <= 1) {
                    console.log('Apenas uma câmera disponível');
                    return;
                }

                stopScanner();
                
                // Avança para próxima câmera (circular)
                currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
                console.log(`🔄 Trocando para câmera [${currentCameraIndex}]: ${availableCameras[currentCameraIndex].label}`);
                
                // Pequeno delay para garantir que a câmera anterior foi liberada
                setTimeout(() => {
                    startScanner(currentCameraIndex);
                }, 300);
            }

            // ===== EVENT LISTENERS =====
            
            // Botão de trocar câmera
            const switchBtn = document.getElementById('switch-camera-btn');
            if (switchBtn) {
                switchBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    switchCamera();
                });
            }

            // ===== INICIALIZAÇÃO =====
            startScanner();

            // ===== EVENTOS LIVEWIRE =====
            Livewire.on('reset-scanner', () => {
                setTimeout(() => startScanner(currentCameraIndex), 300);
            });
            
            Livewire.on('reset-scanner-error', () => {
                setTimeout(() => startScanner(currentCameraIndex), 1200);
            });

            // ===== LIMPEZA =====
            window.addEventListener('beforeunload', stopScanner);
            window.addEventListener('pagehide', stopScanner);
        });
    </script>
</x-filament-panels::page>