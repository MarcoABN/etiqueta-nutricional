<x-filament-panels::page>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* Layout Fullscreen e Reset de Estilos do Filament */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; color: white; }
        .app-container { height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 10; }
        
        /* Scanner Area */
        #scanner-view { position: absolute; inset: 0; z-index: 20; background: #000; overflow: hidden; }
        #reader { width: 100%; height: 100%; background: #000; }
        #reader video { object-fit: cover !important; width: 100% !important; height: 100% !important; }
        
        /* UI Elements */
        .btn-switch-camera {
            position: absolute; top: 25px; right: 25px; z-index: 60;
            width: 50px; height: 50px; border-radius: 50%;
            background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.2); color: white;
            cursor: pointer;
        }

        .scan-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 280px; height: 180px; pointer-events: none; z-index: 30; box-shadow: 0 0 0 9999px rgba(0,0,0,0.7); border-radius: 20px; border: 2px solid rgba(255,255,255,0.5); }
        .scan-line { position: absolute; width: 100%; height: 2px; background: #22c55e; box-shadow: 0 0 15px #22c55e; animation: scanning 2s infinite ease-in-out; }
        @keyframes scanning { 0% {top: 10%;} 50% {top: 90%;} 100% {top: 10%;} }

        /* Photo View */
        #photo-view { position: absolute; inset: 0; z-index: 40; background: #111; display: flex; flex-direction: column; }
        .product-info { background: #1f2937; padding: 25px 20px; border-bottom: 1px solid #374151; }
        .btn-capture { background: #22c55e; color: #000; padding: 20px; border-radius: 16px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 12px; width: 85%; margin: 0 auto; }
        
        /* Footer */
        .footer-actions { padding: 20px; background: #000; display: flex; gap: 12px; border-top: 1px solid #333; }
        .btn-save { background: #22c55e; flex: 2; height: 55px; border-radius: 12px; color: #000; font-weight: bold; font-size: 16px; }
        .btn-save:disabled { background: #1a5e32; opacity: 0.5; color: #555; cursor: not-allowed; }
        .btn-cancel { background: #374151; flex: 1; height: 55px; border-radius: 12px; color: #fff; font-weight: 600; }

        /* Crop Overlay */
        #crop-overlay { position: fixed; inset: 0; background: #000; z-index: 100; display: none; flex-direction: column; }
        .crop-area { flex: 1; position: relative; width: 100%; background: #000; }
        #image-to-crop { display: block; max-width: 100%; }
        
        /* Input invisível manual */
        #manual-camera-input { display: none; }
    </style>

    <div class="app-container" x-data="scannerApp()" x-init="initApp()">
        <input type="file" id="manual-camera-input" accept="image/*" capture="environment">

        <div class="hidden" wire:ignore>
            {{ $this->form }}
        </div>

        <div x-show="!$wire.foundProduct" class="btn-switch-camera" @click="switchCamera()">
            <x-heroicon-o-arrow-path class="w-6 h-6" />
        </div>

        <div id="scanner-view" x-show="!$wire.foundProduct" wire:ignore>
            <div id="reader"></div>
            <div class="scan-frame"><div class="scan-line"></div></div>
        </div>

        <template x-if="$wire.foundProduct">
            <div id="photo-view">
                <div class="product-info">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-green-500 text-xs font-bold tracking-widest uppercase">EAN: <span x-text="$wire.scannedCode"></span></span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-white" x-text="$wire.foundProduct?.product_name"></h2>
                </div>

                <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mb-8 border border-white/10">
                        <x-heroicon-o-camera x-show="status === 'idle'" class="w-10 h-10 text-gray-400" />
                        <svg x-show="status === 'loading'" class="animate-spin h-10 w-10 text-green-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <x-heroicon-s-check-circle x-show="status === 'success'" class="w-12 h-12 text-green-500" />
                    </div>

                    <button type="button" @click="triggerCameraInput()" class="btn-capture shadow-lg active:scale-95 transition-transform">
                        <x-heroicon-s-camera class="w-6 h-6" />
                        TIRAR FOTO DA TABELA
                    </button>
                    <p class="text-gray-500 mt-4 text-xs max-w-[200px]">Use boa iluminação e foque apenas na tabela nutricional.</p>
                </div>

                <div class="footer-actions">
                    <button type="button" @click="resetFlow()" class="btn-cancel">VOLTAR</button>
                    <button type="button" @click="saveData()" :disabled="status !== 'success'" class="btn-save">SALVAR</button>
                </div>
            </div>
        </template>

        <div id="crop-overlay">
            <div class="crop-area">
                <img id="image-to-crop" src="">
            </div>
            <div class="footer-actions" style="z-index: 101;">
                <button type="button" @click="cancelCrop()" class="btn-cancel">CANCELAR</button>
                <button type="button" @click="confirmCrop()" class="btn-save">RECORTAR</button>
            </div>
        </div>
    </div>

    <script>
        function scannerApp() {
            return {
                status: 'idle',
                html5QrCode: null,
                cropper: null,
                cameraId: null,
                cameras: [],
                isScannerRunning: false,

                initApp() {
                    // Inicializa o scanner na primeira carga
                    this.$nextTick(() => {
                        this.startScanner();
                    });

                    // Escuta o evento de reset vindo do Livewire (PHP)
                    Livewire.on('reset-scanner', () => {
                        this.status = 'idle';
                        this.restartScanner();
                    });

                    // Configura o input manual de arquivo
                    const manualInput = document.getElementById('manual-camera-input');
                    manualInput.addEventListener('change', (e) => {
                        if (e.target.files && e.target.files[0]) {
                            const reader = new FileReader();
                            reader.onload = (event) => {
                                this.openCropUI(event.target.result);
                                // Limpa o input para permitir selecionar a mesma foto se necessário
                                manualInput.value = ''; 
                            };
                            reader.readAsDataURL(e.target.files[0]);
                        }
                    });
                },

                async getCameras() {
                    try {
                        const devices = await Html5Qrcode.getCameras();
                        // Filtra câmeras traseiras
                        this.cameras = devices.filter(d => 
                            d.label.toLowerCase().includes('back') || 
                            d.label.toLowerCase().includes('traseira') || 
                            d.label.toLowerCase().includes('environment')
                        );
                        // Fallback se não achar traseira específica
                        if (this.cameras.length === 0) this.cameras = devices;
                        
                        // Define a primeira câmera se não houver uma selecionada
                        if (!this.cameraId && this.cameras.length > 0) {
                            this.cameraId = this.cameras[0].id;
                        }
                    } catch (err) {
                        console.error("Erro ao listar câmeras", err);
                    }
                },

                async startScanner() {
                    // Se já estiver rodando, não faz nada
                    if (this.isScannerRunning) return;
                    
                    // Se o produto já foi encontrado, não inicia o scanner
                    if (this.$wire.foundProduct) return;

                    await this.getCameras();

                    // Cria instância se não existir
                    if (!this.html5QrCode) {
                        this.html5QrCode = new Html5Qrcode("reader");
                    }

                    const config = {
                        fps: 10,
                        qrbox: { width: 250, height: 150 },
                        aspectRatio: 1.0,
                        videoConstraints: {
                            focusMode: 'continuous', // Tenta focar automaticamente
                        }
                    };

                    try {
                        // Se temos um ID específico, usa ele. Se não, usa 'environment'
                        const cameraConfig = this.cameraId ? { deviceId: { exact: this.cameraId } } : { facingMode: "environment" };

                        await this.html5QrCode.start(
                            cameraConfig,
                            config,
                            (decodedText) => {
                                // Sucesso na leitura
                                this.stopScanner().then(() => {
                                    this.$wire.handleBarcodeScan(decodedText);
                                });
                            },
                            (errorMessage) => {
                                // Ignora erros de leitura frame a frame
                            }
                        );
                        this.isScannerRunning = true;
                    } catch (err) {
                        console.error("Erro ao iniciar câmera: ", err);
                        this.isScannerRunning = false;
                    }
                },

                async stopScanner() {
                    if (this.html5QrCode && this.isScannerRunning) {
                        try {
                            await this.html5QrCode.stop();
                            this.isScannerRunning = false;
                            this.html5QrCode.clear();
                        } catch (e) {
                            console.warn("Erro ao parar scanner:", e);
                        }
                    }
                },

                // Função segura para reiniciar (stop + start)
                async restartScanner() {
                    await this.stopScanner();
                    // Pequeno delay para liberar o hardware
                    setTimeout(() => {
                        this.html5QrCode = null; // Força nova instância
                        this.startScanner();
                    }, 300);
                },

                async switchCamera() {
                    if (this.cameras.length < 2) return;
                    
                    // Encontra o índice atual e vai para o próximo
                    const currentIndex = this.cameras.findIndex(c => c.id === this.cameraId);
                    const nextIndex = (currentIndex + 1) % this.cameras.length;
                    this.cameraId = this.cameras[nextIndex].id;

                    await this.restartScanner();
                },

                // ================== LÓGICA DE FOTO E CROP ==================

                triggerCameraInput() {
                    document.getElementById('manual-camera-input').click();
                },

                openCropUI(imageSrc) {
                    const overlay = document.getElementById('crop-overlay');
                    const img = document.getElementById('image-to-crop');
                    
                    img.src = imageSrc;
                    overlay.style.display = 'flex';

                    // Inicializa Cropper
                    if (this.cropper) this.cropper.destroy();
                    
                    this.cropper = new Cropper(img, {
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.9,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        background: false,
                    });
                },

                cancelCrop() {
                    document.getElementById('crop-overlay').style.display = 'none';
                    if (this.cropper) {
                        this.cropper.destroy();
                        this.cropper = null;
                    }
                },

                async confirmCrop() {
                    if (!this.cropper) return;

                    this.status = 'loading';
                    
                    // Obtém imagem recortada em Base64
                    const canvas = this.cropper.getCroppedCanvas({
                        maxWidth: 1280,
                        maxHeight: 1280,
                        fillColor: '#fff',
                    });

                    const base64Image = canvas.toDataURL('image/jpeg', 0.85);

                    // Envia para o backend
                    await this.$wire.processCroppedImage(base64Image);

                    this.status = 'success';
                    this.cancelCrop(); // Fecha o modal
                },

                resetFlow() {
                    this.$wire.resetScanner(); // Chama PHP
                    // O PHP emitirá 'reset-scanner', que chamará restartScanner() aqui
                },
                
                saveData() {
                    this.$wire.save();
                }
            }
        }
    </script>
</x-filament-panels::page>