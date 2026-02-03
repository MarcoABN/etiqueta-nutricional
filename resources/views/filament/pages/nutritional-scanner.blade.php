<x-filament-panels::page class="h-full">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* Ajustes para tela cheia e responsividade */
        .fi-main-content { padding: 0 !important; } /* Remove padding padrão do Filament */
        .camera-container {
            width: 100%;
            background: #000;
            border-radius: 0 0 12px 12px;
            overflow: hidden;
            position: relative;
            /* Altura dinâmica para preencher a tela */
            height: 60vh; 
        }
        video { width: 100%; height: 100%; object-fit: cover; }
        
        /* Botão Pulsante */
        .btn-pulse { animation: pulse-animation 2s infinite; }
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0px rgba(34, 197, 94, 0.5); }
            100% { box-shadow: 0 0 0 15px rgba(34, 197, 94, 0); }
        }
    </style>

    {{-- ETAPA 1: SCANNER DE CÓDIGO --}}
    <div x-show="$wire.viewState === 'scan'" class="flex flex-col h-full bg-gray-100 dark:bg-gray-900 p-2">
        <div class="bg-white dark:bg-gray-800 p-3 rounded-t-xl shadow-sm text-center border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-bold text-gray-800 dark:text-white">🔍 Ler Código</h2>
            <p class="text-xs text-gray-500">Aponte para o EAN do produto</p>
        </div>

        <div class="camera-container rounded-b-xl shadow-inner">
            <div id="reader" class="w-full h-full"></div>
            {{-- Mira --}}
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="w-72 h-40 border-2 border-red-500/80 rounded-lg bg-white/5"></div>
            </div>
        </div>
    </div>

    {{-- ETAPA 2: FOTO (LAYOUT OTIMIZADO) --}}
    <div x-show="$wire.viewState === 'capture'" class="flex flex-col h-full bg-gray-100 dark:bg-gray-900" x-cloak>
        
        {{-- 1. Barra de Informações (Compacta) --}}
        <div class="bg-green-600 p-2 shadow-md z-10">
            <div class="flex justify-between items-center text-white">
                <div class="truncate pr-2">
                    <div class="text-[10px] opacity-80 uppercase tracking-wider">Produto Identificado</div>
                    <div class="font-bold text-sm truncate">{{ $scannedProduct?->product_name }}</div>
                </div>
                <div class="text-xs font-mono bg-green-700 px-2 py-1 rounded">
                    {{ $scannedCode }}
                </div>
            </div>
        </div>

        {{-- 2. Barra de Ações (ACIMA da Câmera) --}}
        <div class="grid grid-cols-4 gap-2 p-2 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 z-10">
            {{-- Botão Cancelar (Pequeno) --}}
            <button wire:click="resetScanner" type="button" 
                class="col-span-1 flex flex-col items-center justify-center p-2 rounded-lg bg-red-50 text-red-600 border border-red-100 active:scale-95 transition">
                <span class="text-xl">✕</span>
                <span class="text-[10px] font-bold">Cancelar</span>
            </button>

            {{-- Botão FOTOGRAFAR (Grande e Destacado) --}}
            <button id="btn-capture" type="button" 
                class="col-span-3 flex items-center justify-center gap-2 bg-green-600 text-white rounded-lg shadow-lg btn-pulse active:bg-green-700 active:scale-95 transition p-3">
                <span class="text-2xl">📸</span>
                <span class="font-bold text-lg">SALVAR FOTO</span>
            </button>
        </div>

        {{-- 3. Câmera (Ocupa o resto) --}}
        <div class="relative flex-1 bg-black overflow-hidden">
            <video id="photo-video" autoplay playsinline class="w-full h-full object-cover"></video>
            <canvas id="photo-canvas" class="hidden"></canvas>
            
            {{-- Instrução flutuante --}}
            <div class="absolute bottom-4 left-0 right-0 text-center pointer-events-none">
                <span class="bg-black/60 text-white text-xs px-3 py-1 rounded-full backdrop-blur-sm">
                    Enquadre a Tabela Nutricional
                </span>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let scannerObj = null;
            let photoStream = null;
            const photoVideo = document.getElementById('photo-video');
            const photoCanvas = document.getElementById('photo-canvas');
            const captureBtn = document.getElementById('btn-capture');

            // --- SCANNER ---
            function startScanner() {
                stopPhotoCamera(); // Garante limpeza
                if (!scannerObj) scannerObj = new Html5Qrcode("reader");

                scannerObj.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 150 } },
                    (decodedText) => {
                        scannerObj.stop().then(() => @this.handleBarcodeScan(decodedText));
                    },
                    () => {}
                ).catch(err => console.error("Erro scanner", err));
            }

            // --- FOTO ---
            function startPhotoCamera() {
                const constraints = {
                    video: {
                        facingMode: "environment",
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    }
                };
                navigator.mediaDevices.getUserMedia(constraints)
                    .then(stream => {
                        photoStream = stream;
                        photoVideo.srcObject = stream;
                    })
                    .catch(err => alert("Erro na câmera de foto: " + err));
            }

            function stopPhotoCamera() {
                if (photoStream) {
                    photoStream.getTracks().forEach(track => track.stop());
                    photoStream = null;
                }
            }

            function takePhoto() {
                if (!photoStream) return;
                
                // Feedback visual de clique
                captureBtn.innerHTML = '<span class="animate-spin">⏳</span> Salvando...';
                captureBtn.classList.remove('bg-green-600', 'btn-pulse');
                captureBtn.classList.add('bg-gray-500');

                photoCanvas.width = photoVideo.videoWidth;
                photoCanvas.height = photoVideo.videoHeight;
                const ctx = photoCanvas.getContext('2d');
                ctx.drawImage(photoVideo, 0, 0);
                
                const dataUrl = photoCanvas.toDataURL('image/jpeg', 0.85);
                @this.savePhoto(dataUrl);
                
                // Não precisa chamar stopPhotoCamera aqui, o resetScanner do PHP fará o ciclo
            }

            // Eventos
            captureBtn.addEventListener('click', (e) => {
                e.preventDefault();
                takePhoto();
            });

            Livewire.on('start-scanner', () => {
                // Restaura botão
                captureBtn.innerHTML = '<span class="text-2xl">📸</span><span class="font-bold text-lg">SALVAR FOTO</span>';
                captureBtn.classList.add('bg-green-600', 'btn-pulse');
                captureBtn.classList.remove('bg-gray-500');
                
                setTimeout(startScanner, 500);
            });

            Livewire.on('start-photo-camera', () => setTimeout(startPhotoCamera, 500));
            Livewire.on('resume-scanner', () => setTimeout(startScanner, 1500));

            startScanner();
        });
    </script>
</x-filament-panels::page>