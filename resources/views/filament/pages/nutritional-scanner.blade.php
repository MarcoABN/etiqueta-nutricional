<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        /* === RESET ESTRUTURAL DO FILAMENT === */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-logo, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; font-family: 'Inter', sans-serif; }

        /* === FILEPOND OCULTO MAS FUNCIONAL === */
        /* Essencial para que o clique via JS funcione */
        .filepond--root {
            position: absolute !important; width: 1px !important; height: 1px !important;
            padding: 0 !important; margin: -1px !important; overflow: hidden !important;
            clip: rect(0,0,0,0) !important; white-space: nowrap !important; border: 0 !important;
            opacity: 0 !important; pointer-events: none;
        }

        :root { --primary: #22c55e; --danger: #ef4444; }

        .app-container {
            height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; background: #000;
            display: flex; flex-direction: column;
        }

        /* === VIEW 1: SCANNER === */
        #scanner-view { position: absolute; inset: 0; z-index: 10; background: #000; }
        #reader { width: 100%; height: 100%; object-fit: cover; }
        #reader video { object-fit: cover; width: 100%; height: 100%; }

        /* Elementos Visuais do Scanner */
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

        /* Cabeçalho do Produto */
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

        /* Área Central (Dinâmica) */
        .viewport-area {
            flex: 1; position: relative; display: flex; align-items: center; justify-content: center;
            background: #000; overflow: hidden; padding: 20px;
        }

        /* Estágio 1: Botão de Confirmação */
        .confirm-stage {
            display: flex; flex-direction: column; align-items: center; gap: 20px; text-align: center;
            animation: fadeIn 0.3s ease-out; width: 100%;
        }
        .confirm-btn {
            background: var(--primary); color: #000; border: none;
            padding: 18px 32px; border-radius: 12px; font-size: 16px; font-weight: 700;
            display: flex; align-items: center; gap: 10px; width: 100%; max-width: 320px;
            justify-content: center; box-shadow: 0 4px 20px rgba(34, 197, 94, 0.3);
            transition: transform 0.1s; cursor: pointer;
        }
        .confirm-btn:active { transform: scale(0.96); }

        /* Estágio 2: Preview da Foto */
        .preview-stage {
            width: 100%; height: 100%; display: none; /* Inicialmente oculto */
            flex-direction: column; align-items: center; justify-content: center;
        }
        .preview-image { 
            width: 100%; height: 100%; object-fit: contain; border-radius: 8px; 
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }

        /* Barra de Ações (Rodapé) */
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
            display: none; /* Só aparece após foto */
        }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .hidden { display: none !important; }
    </style>

    <div class="app-container">

        {{-- === VIEW 1: SCANNER === --}}
        <div id="scanner-view" class="{{ $foundProduct ? 'hidden' : '' }}">
            <div id="reader"></div>
            <div class="scan-frame">
                <div class="scan-corner tl"></div><div class="scan-corner tr"></div>
                <div class="scan-corner bl"></div><div class="scan-corner br"></div>
                <div class="scan-line"></div>
            </div>
            <div class="scan-text">Aponte para o código de barras</div>
        </div>

        {{-- === VIEW 2: FLUXO DE CONFIRMAÇÃO E FOTO === --}}
        <div id="photo-view" class="{{ $foundProduct ? '' : 'hidden' }}">
            
            <div class="header-info">
                <h2>{{ $foundProduct?->product_name ?? 'Produto Identificado' }}</h2>
                <span>EAN: {{ $scannedCode }}</span>
            </div>

            <div class="viewport-area">
                
                {{-- ESTÁGIO 1: Confirmação --}}
                <div id="stage-confirm" class="confirm-stage">
                    <p class="text-gray-400 text-sm">Confirme se o produto acima está correto para prosseguir com a foto.</p>
                    
                    {{-- O BOTÃO QUE ABRE A CÂMERA --}}
                    <button id="btn-confirm-capture" class="confirm-btn">
                        <x-heroicon-o-camera class="w-6 h-6"/>
                        CONFIRMAR E FOTOGRAFAR
                    </button>
                </div>

                {{-- ESTÁGIO 2: Preview (Aparece após tirar foto) --}}
                <div id="stage-preview" class="preview-stage">
                    <img id="local-preview" class="preview-image" alt="Preview">
                    <button id="btn-retake" class="text-gray-400 text-xs mt-4 underline p-2">Tirar outra foto</button>
                </div>

                {{-- O Componente Livewire REAL (Totalmente Invisível) --}}
                <div style="position: absolute; opacity: 0; pointer-events: none; width: 0; height: 0;">
                    {{ $this->form }}
                </div>
            </div>

            <div class="controls-bar">
                {{-- Cancelar / Voltar --}}
                <button wire:click="resetScanner" class="icon-btn">
                    <x-heroicon-o-x-mark class="w-6 h-6"/>
                </button>

                {{-- Salvar (Só aparece no Estágio 2) --}}
                <button id="btn-save" wire:click="save" class="icon-btn save-btn">
                    <x-heroicon-m-check class="w-8 h-8"/>
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;

            // Elementos DOM
            const btnConfirm = document.getElementById('btn-confirm-capture');
            const btnRetake = document.getElementById('btn-retake');
            const btnSave = document.getElementById('btn-save');
            const stageConfirm = document.getElementById('stage-confirm');
            const stagePreview = document.getElementById('stage-preview');
            const previewImg = document.getElementById('local-preview');

            // === AÇÃO DE CLICK FÍSICO (Resolve o problema de segurança) ===
            function openCamera() {
                // Busca o input criado pelo Filament
                const fileInput = document.querySelector('input[type="file"].filepond--browser');
                
                if (fileInput) {
                    // Prepara o listener para quando a foto for tirada
                    fileInput.onchange = (e) => {
                        if (e.target.files && e.target.files[0]) {
                            const file = e.target.files[0];
                            
                            // Cria preview imediato
                            previewImg.src = URL.createObjectURL(file);
                            
                            // Troca a UI de "Confirmar" para "Preview"
                            stageConfirm.style.display = 'none';
                            stagePreview.style.display = 'flex';
                            btnSave.style.display = 'flex'; // Mostra botão salvar
                        }
                    };
                    // Abre câmera nativa
                    fileInput.click();
                } else {
                    alert("Erro: Input de câmera não encontrado.");
                }
            }

            // Bind nos botões
            if(btnConfirm) btnConfirm.addEventListener('click', openCamera);
            if(btnRetake) btnRetake.addEventListener('click', openCamera);


            // === LÓGICA DO SCANNER ===
            async function startScanner() {
                if (@json($foundProduct)) return;

                const devices = await Html5Qrcode.getCameras();
                if (!devices || !devices.length) return;

                // Seleção de câmera (Prioriza traseira/não-wide)
                let cameraId = devices[0].id;
                const backCameras = devices.filter(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('traseira'));
                
                if (backCameras.length > 0) {
                    const mainCam = backCameras.find(d => !d.label.toLowerCase().includes('wide'));
                    cameraId = mainCam ? mainCam.id : backCameras[0].id;
                }

                html5QrCode = new Html5Qrcode("reader");
                
                html5QrCode.start(
                    { deviceId: { exact: cameraId } }, 
                    { 
                        fps: 10, 
                        qrbox: { width: 250, height: 150 },
                        aspectRatio: 1.77 
                    },
                    (decodedText) => {
                        stopScanner();
                        // Ao ler, o Livewire atualiza o state e mostra a view #photo-view
                        // O usuário verá o botão "Confirmar"
                        @this.handleBarcodeScan(decodedText);
                    },
                    () => {}
                ).catch(err => console.log(err));
            }

            function stopScanner() {
                if (html5QrCode) {
                    html5QrCode.stop().then(() => html5QrCode.clear()).catch(()=>{});
                }
            }

            // Inicia
            startScanner();

            // Listeners Livewire para resetar a interface visual
            Livewire.on('reset-scanner', () => {
                // Reseta UI para estado inicial
                stagePreview.style.display = 'none';
                stageConfirm.style.display = 'flex';
                btnSave.style.display = 'none';
                previewImg.src = '';
                
                setTimeout(startScanner, 500);
            });
            
            Livewire.on('reset-scanner-error', () => setTimeout(startScanner, 1500));
        });
    </script>
</x-filament-panels::page>