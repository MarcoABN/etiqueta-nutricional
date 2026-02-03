<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* --- RESET AGRESSIVO DO FILAMENT PARA MODO APP --- */
        .fi-layout, .fi-body, .fi-main { height: 100vh !important; padding: 0 !important; margin: 0 !important; }
        .fi-topbar, .fi-sidebar, .fi-header, .fi-breadcrumbs { display: none !important; }
        .fi-content { padding: 0 !important; height: 100vh; overflow: hidden; }
        
        /* Fundo preto estilo câmera nativa */
        main { background-color: #000; height: 100%; display: flex; flex-direction: column; }

        /* --- ESTILO DO FILE UPLOAD (DISFARÇADO DE BOTÃO) --- */
        .filepond--root { margin-bottom: 0 !important; }
        .filepond--panel-root { background-color: transparent !important; border: none !important; }
        .filepond--drop-label { display: none !important; }
        
        /* Área de toque expandida */
        .camera-trigger-area {
            position: absolute; bottom: 40px; left: 0; right: 0;
            display: flex; justify-content: center; align-items: center;
            z-index: 50;
        }

        /* O botão visual */
        .shutter-button {
            width: 72px; height: 72px;
            border-radius: 50%;
            border: 4px solid white;
            background: transparent;
            display: flex; align-items: center; justify-content: center;
            transition: transform 0.1s;
        }
        .shutter-button:active { transform: scale(0.9); background: rgba(255,255,255,0.2); }
        .shutter-inner { width: 56px; height: 56px; background: white; border-radius: 50%; }

        /* Esconde input real mas mantém clicável sobre o botão */
        .filepond--root input { cursor: pointer; }
    </style>

    {{-- HEADER FLUTUANTE --}}
    <div class="absolute top-0 left-0 right-0 z-50 p-4 flex justify-between items-start bg-gradient-to-b from-black/80 to-transparent">
        <div>
            @if($foundProduct)
                <h2 class="text-white font-bold text-lg leading-tight shadow-black drop-shadow-md">
                    {{ $foundProduct->product_name ?? 'Produto Identificado' }}
                </h2>
                <span class="text-gray-300 text-xs font-mono bg-black/50 px-2 py-1 rounded">
                    {{ $scannedCode }}
                </span>
            @else
                <h2 class="text-white font-bold text-xl drop-shadow-md">Scanner</h2>
                <p class="text-gray-300 text-xs">Aponte para o código de barras</p>
            @endif
        </div>

        {{-- Seletor de Câmera (Só aparece se houver múltiplas) --}}
        <div id="camera-select-container" class="hidden">
            <select id="camera-selection" class="bg-black/60 text-white text-xs border border-gray-600 rounded px-2 py-1 outline-none">
            </select>
        </div>
    </div>

    {{-- ÁREA PRINCIPAL --}}
    <div class="relative w-full h-full flex flex-col bg-black">

        {{-- VISÃO 1: SCANNER DE BARRA --}}
        <div id="scanner-wrapper" class="{{ $foundProduct ? 'hidden' : 'block' }} relative w-full h-full">
            <div id="reader" class="w-full h-full object-cover"></div>
            
            {{-- MIRA CENTRAL --}}
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                <div class="w-64 h-40 border-2 border-green-500/70 rounded-lg relative box-content shadow-[0_0_0_9999px_rgba(0,0,0,0.6)]">
                    <div class="absolute top-1/2 left-2 right-2 h-0.5 bg-red-500/50"></div>
                </div>
            </div>
        </div>

        {{-- VISÃO 2: CAPTURA DE FOTO --}}
        <div id="photo-wrapper" class="{{ $foundProduct ? 'flex' : 'hidden' }} flex-1 flex-col relative w-full h-full bg-gray-900">
            
            {{-- Componente de Upload do Filament (Invisível mas funcional) --}}
            <div class="flex-1 relative flex items-center justify-center overflow-hidden">
                {{-- O preview da imagem carregada aparecerá aqui --}}
                <div class="w-full h-full relative z-10 px-4 pt-16 pb-32">
                     {{ $this->form }}
                </div>

                {{-- Placeholder visual antes de tirar a foto --}}
                <div class="absolute inset-0 z-0 flex flex-col items-center justify-center text-gray-500 pointer-events-none">
                    <x-heroicon-o-camera class="w-24 h-24 opacity-20"/>
                    <p class="mt-4 text-sm opacity-50">Toque no botão abaixo para capturar</p>
                </div>
            </div>

            {{-- Botões de Ação Inferiores --}}
            <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-black via-black/90 to-transparent flex items-center justify-between px-8 z-50">
                
                {{-- Cancelar / Voltar --}}
                <button wire:click="resetScanner" type="button" class="text-white p-4 rounded-full bg-gray-800/80 hover:bg-gray-700">
                    <x-heroicon-o-x-mark class="w-6 h-6"/>
                </button>

                {{-- Botão FAKE do Shutter (O clique real acontece no FileUpload transparente acima, mas visualmente é aqui) --}}
                {{-- Nota: O FileUpload do Filament com capture='environment' cria um botão 'Choose File'. 
                     Vamos instruir o usuário visualmente. O FileUpload acima deve ser estilizado para ocupar essa área ou ser o trigger. --}}
                <div class="pointer-events-none">
                    <div class="shutter-button">
                        <div class="shutter-inner"></div>
                    </div>
                </div>

                {{-- Salvar (Upload) --}}
                <button wire:click="save" type="button" class="text-black bg-green-500 p-4 rounded-full hover:bg-green-400 shadow-lg shadow-green-500/30">
                    <x-heroicon-m-check class="w-6 h-6"/>
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;
            const scannerContainerId = "reader";

            async function initScanner() {
                // Se já identificou produto, para o scanner e economiza bateria
                if (@json($foundProduct)) {
                    stopScanner();
                    return;
                }

                try {
                    // 1. Pega lista de câmeras
                    const devices = await Html5Qrcode.getCameras();
                    if (!devices || devices.length === 0) {
                        alert("Nenhuma câmera encontrada.");
                        return;
                    }

                    // 2. Lógica inteligente de seleção de câmera
                    let cameraId = devices[0].id; // Fallback
                    
                    // Filtra câmeras traseiras
                    // Nota: Browsers mobile nem sempre retornam label correta, mas tentamos
                    const backCameras = devices.filter(d => 
                        d.label.toLowerCase().includes('back') || 
                        d.label.toLowerCase().includes('traseira') ||
                        d.label.toLowerCase().includes('environment')
                    );

                    if (backCameras.length > 0) {
                        // Tenta achar a principal (evita 'wide', 'macro' se possível)
                        const mainCamera = backCameras.find(d => 
                            !d.label.toLowerCase().includes('wide') && 
                            !d.label.toLowerCase().includes('macro')
                        );
                        cameraId = mainCamera ? mainCamera.id : backCameras[backCameras.length - 1].id;
                        
                        // Se tivermos mais de uma opção traseira válida, preenche o select
                        if (backCameras.length > 1) {
                            populateCameraSelect(backCameras, cameraId);
                        }
                    } else {
                        // Se não detectou labels, usa a última da lista (geralmente a traseira em Androids antigos)
                        cameraId = devices[devices.length - 1].id;
                    }

                    startStream(cameraId);

                } catch (err) {
                    console.error("Erro ao iniciar câmera", err);
                    // Fallback para modo automático se falhar permissão de listar devices
                    startStream(null); 
                }
            }

            function populateCameraSelect(cameras, currentId) {
                const select = document.getElementById('camera-selection');
                const container = document.getElementById('camera-select-container');
                
                select.innerHTML = '';
                cameras.forEach(cam => {
                    const opt = document.createElement('option');
                    opt.value = cam.id;
                    opt.text = cam.label || `Câmera ${cam.id.substr(0,4)}`;
                    if(cam.id === currentId) opt.selected = true;
                    select.appendChild(opt);
                });

                container.classList.remove('hidden');
                
                // Evento de troca manual
                select.onchange = (e) => {
                    stopScanner().then(() => startStream(e.target.value));
                };
            }

            function startStream(cameraId) {
                html5QrCode = new Html5Qrcode(scannerContainerId);
                
                const config = { 
                    fps: 10, 
                    qrbox: { width: 250, height: 150 },
                    aspectRatio: window.innerWidth / window.innerHeight
                };

                // Se temos ID específico usamos ele, senão usamos facingMode
                const cameraConfig = cameraId ? { deviceId: { exact: cameraId } } : { facingMode: "environment" };

                html5QrCode.start(
                    cameraConfig,
                    config,
                    (decodedText) => {
                        stopScanner();
                        // Toca um som de beep (opcional)
                        // new Audio('/beep.mp3').play().catch(e=>{});
                        @this.handleBarcodeScan(decodedText);
                    },
                    (errorMessage) => { /* ignora erros de leitura por frame */ }
                ).catch(err => {
                    console.log(err);
                });
            }

            function stopScanner() {
                if (html5QrCode) {
                    return html5QrCode.stop().then(() => {
                        html5QrCode.clear();
                        html5QrCode = null;
                    }).catch(err => {});
                }
                return Promise.resolve();
            }

            // Iniciar
            initScanner();

            // Listeners do Livewire
            Livewire.on('start-scanner', () => setTimeout(initScanner, 300));
            Livewire.on('resume-scanner', () => setTimeout(initScanner, 1500));
        });
    </script>
</x-filament-panels::page>