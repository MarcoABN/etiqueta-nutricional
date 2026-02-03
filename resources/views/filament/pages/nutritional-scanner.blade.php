<x-filament-panels::page class="h-full">
    {{-- Biblioteca de Scanner --}}
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        .camera-container {
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            position: relative;
            aspect-ratio: 3/4; /* Formato retrato típico de celular */
        }
        video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>

    {{-- ETAPA 1: SCANNER DE CÓDIGO DE BARRAS --}}
    <div x-show="$wire.viewState === 'scan'" class="flex flex-col space-y-4">
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow mb-2 text-center">
            <h2 class="text-lg font-bold">1. Ler Código de Barras</h2>
            <p class="text-xs text-gray-500">Aponte a câmera para o EAN do produto</p>
        </div>

        <div class="camera-container shadow-lg">
            <div id="reader" class="w-full h-full"></div>
            {{-- Mira --}}
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="w-64 h-32 border-2 border-red-500/50 rounded-lg"></div>
            </div>
        </div>
    </div>

    {{-- ETAPA 2: CAPTURA DE FOTO (TABELA) --}}
    <div x-show="$wire.viewState === 'capture'" class="flex flex-col space-y-4" x-cloak>
        
        {{-- Card do Produto Encontrado --}}
        <div class="p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
            <div class="text-xs font-bold text-green-700 uppercase">Produto Identificado</div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                {{ $scannedProduct?->product_name }}
            </h3>
        </div>

        <div class="bg-white dark:bg-gray-800 p-2 rounded-lg text-center">
            <h2 class="text-lg font-bold">2. Fotografar Tabela</h2>
            <p class="text-xs text-gray-500">Certifique-se que os números estão legíveis</p>
        </div>

        {{-- Câmera de Alta Resolução --}}
        <div class="camera-container shadow-lg">
            <video id="photo-video" autoplay playsinline></video>
            <canvas id="photo-canvas" class="hidden"></canvas> </div>

        {{-- Botões de Ação --}}
        <div class="grid grid-cols-2 gap-4">
            <x-filament::button 
                color="danger" 
                size="xl" 
                outlined 
                wire:click="resetScanner">
                Cancelar
            </x-filament::button>

            <x-filament::button 
                color="success" 
                size="xl" 
                class="w-full"
                id="btn-capture">
                📸 FOTOGRAFAR
            </x-filament::button>
        </div>
    </div>

    {{-- SCRIPTS DE CONTROLE --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            // --- VARIÁVEIS DO SCANNER (ETAPA 1) ---
            let scannerObj = null;
            
            // --- VARIÁVEIS DA FOTO (ETAPA 2) ---
            let photoStream = null;
            const photoVideo = document.getElementById('photo-video');
            const photoCanvas = document.getElementById('photo-canvas');
            const captureBtn = document.getElementById('btn-capture');

            // === FUNÇÕES ETAPA 1: SCANNER ===
            function startScanner() {
                // Garante que a câmera de foto está desligada
                stopPhotoCamera();

                if (!scannerObj) {
                    scannerObj = new Html5Qrcode("reader");
                }

                scannerObj.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 150 } },
                    (decodedText) => {
                        console.log("Lido:", decodedText);
                        scannerObj.stop().then(() => {
                             @this.handleBarcodeScan(decodedText);
                        });
                    },
                    (errorMessage) => { /* ignora erros de frame vazio */ }
                ).catch(err => {
                    console.error("Erro scanner", err);
                    alert("Erro ao iniciar câmera de leitura. Verifique permissões HTTPS.");
                });
            }

            // === FUNÇÕES ETAPA 2: FOTO ALTA RESOLUÇÃO ===
            function startPhotoCamera() {
                // Tenta pegar a melhor resolução possível (HD/Full HD)
                const constraints = {
                    video: {
                        facingMode: "environment",
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    }
                };

                navigator.mediaDevices.getUserMedia(constraints)
                    .then((stream) => {
                        photoStream = stream;
                        photoVideo.srcObject = stream;
                    })
                    .catch((err) => {
                        console.error("Erro câmera foto", err);
                        alert("Não foi possível acessar a câmera para foto.");
                    });
            }

            function stopPhotoCamera() {
                if (photoStream) {
                    photoStream.getTracks().forEach(track => track.stop());
                    photoStream = null;
                }
            }

            function takePhoto() {
                if (!photoStream) return;

                // Configura o canvas para o tamanho real do vídeo
                photoCanvas.width = photoVideo.videoWidth;
                photoCanvas.height = photoVideo.videoHeight;
                
                // Desenha o frame atual no canvas
                const ctx = photoCanvas.getContext('2d');
                ctx.drawImage(photoVideo, 0, 0, photoCanvas.width, photoCanvas.height);
                
                // Converte para Base64 (JPG qualidade 0.8)
                const dataUrl = photoCanvas.toDataURL('image/jpeg', 0.85);

                // Envia para o Backend
                @this.savePhoto(dataUrl);
                
                // Desliga câmera
                stopPhotoCamera();
            }

            // === EVENTOS / GATILHOS ===
            
            // Botão de Captura (JS puro para evitar delay do Livewire no clique)
            captureBtn.addEventListener('click', (e) => {
                e.preventDefault();
                captureBtn.innerText = "Processando...";
                captureBtn.disabled = true;
                takePhoto();
            });

            // Ganchos do Livewire
            Livewire.on('start-scanner', () => {
                setTimeout(startScanner, 500); // Delay para DOM atualizar
                captureBtn.innerText = "📸 FOTOGRAFAR";
                captureBtn.disabled = false;
            });

            Livewire.on('start-photo-camera', () => {
                setTimeout(startPhotoCamera, 500);
            });

            Livewire.on('resume-scanner', () => {
                // Se der erro no produto, volta a escanear
                setTimeout(startScanner, 1000);
            });

            // Inicia o scanner ao carregar a página
            startScanner();
        });
    </script>
</x-filament-panels::page>