<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* --- RESET E LAYOUT FULLSCREEN --- */
        .fi-layout, .fi-body, .fi-main { height: 100vh !important; padding: 0 !important; margin: 0 !important; }
        .fi-topbar, .fi-sidebar, .fi-header, .fi-breadcrumbs { display: none !important; }
        .fi-content { padding: 0 !important; height: 100vh; overflow: hidden; }
        
        main { background-color: #000; height: 100%; display: flex; flex-direction: column; position: relative; }

        /* --- VIDEO FULLSCREEN --- */
        #scanner-wrapper { position: absolute; inset: 0; z-index: 0; background: #000; }
        #reader { width: 100%; height: 100%; object-fit: cover; }
        #reader video { object-fit: cover; width: 100%; height: 100%; }

        /* --- UI OVERLAY (CABEÇALHO) --- */
        .camera-overlay-header {
            position: absolute; top: 0; left: 0; right: 0; z-index: 20;
            padding: 20px;
            /* Gradiente para garantir leitura do texto branco sobre qualquer fundo */
            background: linear-gradient(to bottom, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.4) 60%, transparent 100%);
            display: flex; flex-direction: column; gap: 10px;
        }

        /* --- SELECT CUSTOMIZADO --- */
        .custom-select-wrapper {
            position: relative;
            display: inline-block;
            max-width: 100%;
        }
        .custom-select {
            appearance: none;
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 35px 8px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            width: 100%;
            outline: none;
            backdrop-filter: blur(4px);
        }
        .custom-select:focus { background-color: rgba(255, 255, 255, 0.3); border-color: white; }
        .select-icon {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            color: white; pointer-events: none;
        }

        /* --- ÁREA DE FOTO (FilePond Disfarçado) --- */
        .filepond--root { margin-bottom: 0 !important; height: 100%; }
        .filepond--panel-root { background-color: transparent !important; border: none !important; }
        .filepond--drop-label { display: none !important; }
        
        /* Botão Shutter */
        .shutter-button {
            width: 76px; height: 76px;
            border-radius: 50%;
            border: 4px solid white;
            background: transparent;
            display: flex; align-items: center; justify-content: center;
            transition: transform 0.1s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }
        .shutter-button:active { transform: scale(0.92); }
        .shutter-inner { width: 60px; height: 60px; background: white; border-radius: 50%; }
    </style>

    {{-- CABEÇALHO FLUTUANTE (Sempre visível no Scanner) --}}
    @if(!$foundProduct)
    <div class="camera-overlay-header">
        <div class="flex justify-between items-start w-full">
            <div>
                <h2 class="text-white font-bold text-xl drop-shadow-md tracking-wide">SCANNER</h2>
                <p class="text-gray-200 text-xs font-medium mt-1">Aponte a câmera para o código</p>
            </div>
        </div>

        {{-- Seletor de Câmera (Oculto até carregar dispositivos) --}}
        <div id="camera-select-container" class="hidden w-full mt-2">
            <div class="custom-select-wrapper w-full">
                <select id="camera-selection" class="custom-select">
                    {{-- Options via JS --}}
                </select>
                <x-heroicon-m-chevron-down class="select-icon w-4 h-4"/>
            </div>
        </div>
    </div>
    @endif

    {{-- CONTEÚDO PRINCIPAL --}}
    <div class="w-full h-full relative bg-black">

        {{-- MODO 1: SCANNER --}}
        <div id="scanner-wrapper" class="{{ $foundProduct ? 'hidden' : 'block' }}">
            {{-- Vídeo --}}
            <div id="reader"></div>
            
            {{-- MIRA (Centralizada) --}}
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center z-10">
                <div class="w-72 h-44 border-2 border-white/50 rounded-xl relative shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]">
                    {{-- Cantos da mira --}}
                    <div class="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-green-500 -mt-1 -ml-1 rounded-tl-lg"></div>
                    <div class="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-green-500 -mt-1 -mr-1 rounded-tr-lg"></div>
                    <div class="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-green-500 -mb-1 -ml-1 rounded-bl-lg"></div>
                    <div class="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-green-500 -mb-1 -mr-1 rounded-br-lg"></div>
                    
                    {{-- Linha de Scan --}}
                    <div class="absolute top-1/2 left-4 right-4 h-0.5 bg-red-500/80 shadow-[0_0_8px_rgba(239,68,68,0.8)] animate-pulse"></div>
                </div>
            </div>
        </div>

        {{-- MODO 2: FOTO DO PRODUTO --}}
        <div id="photo-wrapper" class="{{ $foundProduct ? 'flex' : 'hidden' }} flex-col w-full h-full bg-gray-900 z-30 absolute inset-0">
            
            {{-- Info do Produto --}}
            <div class="absolute top-0 left-0 right-0 z-20 p-4 bg-gradient-to-b from-black/90 to-transparent">
                <h2 class="text-white font-bold text-lg leading-tight shadow-black drop-shadow-md">
                    {{ $foundProduct?->product_name ?? 'Produto Identificado' }}
                </h2>
                <span class="text-green-400 text-xs font-mono font-bold bg-green-900/30 border border-green-500/30 px-2 py-0.5 rounded mt-1 inline-block">
                    EAN: {{ $scannedCode }}
                </span>
            </div>

            {{-- Área da Câmera (FileUpload) --}}
            <div class="flex-1 relative flex items-center justify-center overflow-hidden bg-black">
                {{-- O formulário do Filament --}}
                <div class="w-full h-full relative z-10 px-0 pt-0 pb-0 flex flex-col justify-center">
                     {{ $this->form }}
                </div>

                {{-- Placeholder visual para quando não tem imagem ainda --}}
                <div class="absolute inset-0 z-0 flex flex-col items-center justify-center text-gray-500 pointer-events-none opacity-30">
                    <x-heroicon-o-camera class="w-20 h-20"/>
                </div>
            </div>

            {{-- Controles Inferiores --}}
            <div class="absolute bottom-0 left-0 right-0 h-36 bg-gradient-to-t from-black via-black/90 to-transparent flex items-end justify-between px-8 pb-8 z-50 pointer-events-none">
                
                {{-- Botão Voltar --}}
                <button wire:click="resetScanner" type="button" class="pointer-events-auto text-white w-12 h-12 rounded-full bg-gray-800/80 flex items-center justify-center hover:bg-gray-700 backdrop-blur">
                    <x-heroicon-o-x-mark class="w-6 h-6"/>
                </button>

                {{-- Visual do Shutter (Trigger) --}}
                <div class="pointer-events-none mb-2">
                    <div class="shutter-button">
                        <div class="shutter-inner"></div>
                    </div>
                </div>

                {{-- Botão Salvar --}}
                <button wire:click="save" type="button" class="pointer-events-auto text-black bg-green-500 w-12 h-12 rounded-full flex items-center justify-center hover:bg-green-400 shadow-lg shadow-green-500/30 transition-all transform active:scale-95">
                    <x-heroicon-m-check class="w-7 h-7"/>
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;
            const scannerContainerId = "reader";
            const STORAGE_KEY = 'app_scanner_pref_camera'; // Chave para salvar a escolha

            async function initScanner() {
                if (@json($foundProduct)) return;

                try {
                    const devices = await Html5Qrcode.getCameras();
                    
                    if (!devices || devices.length === 0) {
                        alert("Nenhuma câmera encontrada.");
                        return;
                    }

                    // --- LÓGICA DE SELEÇÃO E PERSISTÊNCIA ---
                    
                    // 1. Tenta recuperar a última câmera usada
                    let savedCameraId = localStorage.getItem(STORAGE_KEY);
                    let selectedCameraId = null;

                    // 2. Verifica se a câmera salva ainda existe na lista atual
                    if (savedCameraId && devices.some(d => d.id === savedCameraId)) {
                        selectedCameraId = savedCameraId;
                    } else {
                        // 3. Se não tem salva, usa a CÂMERA 0 (primeira da lista) como padrão
                        selectedCameraId = devices[0].id;
                    }

                    // 4. Popula o menu (mesmo se já escolheu, para permitir troca)
                    populateCameraSelect(devices, selectedCameraId);

                    // 5. Inicia
                    startStream(selectedCameraId);

                } catch (err) {
                    console.error("Erro Câmera:", err);
                    // Fallback para automático se der erro ao listar
                    startStream(null); 
                }
            }

            function populateCameraSelect(cameras, currentId) {
                const select = document.getElementById('camera-selection');
                const container = document.getElementById('camera-select-container');
                
                if(!select) return;

                select.innerHTML = '';
                
                cameras.forEach((cam, index) => {
                    const opt = document.createElement('option');
                    opt.value = cam.id;
                    // Tenta limpar o nome da câmera ou usa "Câmera X"
                    let label = cam.label || `Câmera ${index + 1}`;
                    // Simplifica nomes muito longos se necessário
                    label = label.replace(/\(.*\)/, '').trim(); 
                    
                    opt.text = (cam.id === currentId ? 'Currently: ' : '') + label;
                    if(cam.id === currentId) opt.selected = true;
                    select.appendChild(opt);
                });

                // Mostra o menu se houver mais de 1 câmera
                if (cameras.length > 1) {
                    container.classList.remove('hidden');
                }
                
                // Evento de troca manual + SALVAR PREFERÊNCIA
                select.onchange = (e) => {
                    const newId = e.target.value;
                    // Salva a escolha do usuário
                    localStorage.setItem(STORAGE_KEY, newId);
                    
                    stopScanner().then(() => startStream(newId));
                };
            }

            function startStream(cameraId) {
                html5QrCode = new Html5Qrcode(scannerContainerId);
                
                const config = { 
                    fps: 10, 
                    qrbox: { width: 250, height: 150 },
                    aspectRatio: window.innerHeight / window.innerWidth // Inverte para retrato
                };

                const cameraConfig = cameraId ? { deviceId: { exact: cameraId } } : { facingMode: "environment" };

                html5QrCode.start(
                    cameraConfig,
                    config,
                    (decodedText) => {
                        stopScanner();
                        @this.handleBarcodeScan(decodedText);
                    },
                    (errorMessage) => {}
                ).catch(err => console.log(err));
            }

            function stopScanner() {
                if (html5QrCode) {
                    return html5QrCode.stop().then(() => {
                        html5QrCode.clear();
                    }).catch(err => {});
                }
                return Promise.resolve();
            }

            initScanner();

            Livewire.on('start-scanner', () => setTimeout(initScanner, 300));
            Livewire.on('resume-scanner', () => setTimeout(initScanner, 1000));
        });
    </script>
</x-filament-panels::page>