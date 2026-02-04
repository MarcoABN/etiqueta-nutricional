<x-filament-panels::page>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* CSS Reset para Fullscreen */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; color: white; }
        
        .app-container { height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 10; background: #000; }
        
        /* Scanner Visuals */
        #scanner-view { position: absolute; inset: 0; z-index: 20; background: #000; }
        #reader { width: 100%; height: 100%; }
        /* Força o video a preencher a tela */
        #reader video { object-fit: cover !important; width: 100% !important; height: 100% !important; }
        
        .scan-frame { 
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
            width: 80%; max-width: 300px; height: 200px; 
            pointer-events: none; z-index: 30; 
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.7); 
            border-radius: 16px; border: 2px solid rgba(255,255,255,0.6); 
        }
        .scan-line { position: absolute; width: 100%; height: 2px; background: #22c55e; animation: scanning 2s infinite linear; }
        @keyframes scanning { 0% {top: 5%;} 50% {top: 95%;} 100% {top: 5%;} }

        .btn-switch-camera {
            position: absolute; top: 30px; right: 30px; z-index: 60;
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.3);
            display: flex; align-items: center; justify-content: center; color: white;
            cursor: pointer;
        }

        /* Product Found / Photo View */
        #photo-view { position: absolute; inset: 0; z-index: 40; background: #111; display: flex; flex-direction: column; }
        .info-header { padding: 30px 20px; background: #1f2937; border-bottom: 1px solid #374151; }
        
        .capture-area { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px; }
        .btn-capture { 
            background: #22c55e; color: #000; padding: 20px 30px; 
            border-radius: 50px; font-weight: 800; font-size: 16px;
            display: flex; align-items: center; gap: 10px; 
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4);
        }

        .footer-actions { padding: 20px; background: #000; display: flex; gap: 15px; border-top: 1px solid #333; }
        .btn-base { flex: 1; height: 56px; border-radius: 12px; font-weight: bold; font-size: 16px; }
        .btn-cancel { background: #374151; color: white; }
        .btn-save { background: #22c55e; color: black; flex: 2; }
        .btn-save:disabled { background: #1f4e30; color: #666; opacity: 0.7; }

        /* Crop UI */
        #crop-overlay { position: fixed; inset: 0; background: #000; z-index: 100; display: none; flex-direction: column; }
        .crop-stage { flex: 1; position: relative; overflow: hidden; background: #000; }
        #image-to-crop { display: block; max-width: 100%; }

        /* Inputs invisíveis */
        #manual-input { display: none; }
    </style>

    <div class="app-container" x-data="scannerApp()" x-init="init()">
        <input type="file" id="manual-input" accept="image/*" capture="environment">

        <div x-show="!$wire.foundProduct" class="btn-switch-camera" @click="cycleCamera()">
            <x-heroicon-o-arrow-path class="w-6 h-6" />
        </div>

        <div id="scanner-view" x-show="!$wire.foundProduct" wire:ignore>
            <div id="reader"></div>
            <div class="scan-frame"><div class="scan-line"></div></div>
        </div>

        <template x-if="$wire.foundProduct">
            <div id="photo-view">
                <div class="info-header">
                    <div class="text-green-500 text-xs font-bold uppercase tracking-widest mb-1">EAN: <span x-text="$wire.scannedCode"></span></div>
                    <h1 class="text-xl font-bold text-white leading-tight" x-text="$wire.foundProduct?.product_name"></h1>
                </div>

                <div class="capture-area">
                    <div class="w-24 h-24 rounded-full bg-white/5 flex items-center justify-center border border-white/10">
                        <x-heroicon-o-camera x-show="status === 'idle'" class="w-10 h-10 text-gray-400" />
                        <svg x-show="status === 'loading'" class="animate-spin h-10 w-10 text-green-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <x-heroicon-s-check-circle x-show="status === 'success'" class="w-12 h-12 text-green-500" />
                    </div>

                    <button @click="triggerInput()" class="btn-capture active:scale-95 transition-transform">
                        <x-heroicon-s-camera class="w-6 h-6" />
                        <span>FOTO DA TABELA</span>
                    </button>
                    <p class="text-gray-500 text-xs">Foque bem nos números</p>
                </div>

                <div class="footer-actions">
                    <button @click="reset()" class="btn-base btn-cancel">VOLTAR</button>
                    <button @click="save()" :disabled="status !== 'success'" class="btn-base btn-save">SALVAR</button>
                </div>
            </div>
        </template>

        <div id="crop-overlay">
            <div class="crop-stage">
                <img id="image-to-crop" src="">
            </div>
            <div class="footer-actions" style="z-index: 101;">
                <button @click="cancelCrop()" class="btn-base btn-cancel">CANCELAR</button>
                <button @click="confirmCrop()" class="btn-base btn-save">RECORTAR</button>
            </div>
        </div>
    </div>

    <script>
        function scannerApp() {
            return {
                status: 'idle',
                html5QrCode: null,
                cropper: null,
                selectedCameraId: null, // Armazena ID específico se o usuário trocar
                allCameras: [],

                init() {
                    // Inicia scanner na montagem
                    this.$nextTick(() => this.startScanner());

                    // Listener para reset vindo do PHP
                    Livewire.on('reset-scanner-ui', () => {
                        this.status = 'idle';
                        this.startScanner();
                    });

                    // Configura o input de arquivo manual
                    const input = document.getElementById('manual-input');
                    input.addEventListener('change', (e) => {
                        if (e.target.files && e.target.files[0]) {
                            const reader = new FileReader();
                            reader.onload = (evt) => this.openCrop(evt.target.result);
                            reader.readAsDataURL(e.target.files[0]);
                            input.value = ''; // Reseta para permitir selecionar o mesmo arquivo
                        }
                    });
                },

                async startScanner() {
                    if (this.$wire.foundProduct) return;
                    
                    // Limpa instância anterior
                    if (this.html5QrCode) {
                        try { await this.html5QrCode.stop(); } catch(e) {}
                        this.html5QrCode = null;
                    }

                    this.html5QrCode = new Html5Qrcode("reader");

                    const config = { 
                        fps: 20, // Mais quadros por segundo ajuda na leitura rápida
                        qrbox: { width: 250, height: 150 }, // Área de leitura
                        aspectRatio: 1.0 
                    };

                    try {
                        // ESTRATÉGIA DE INICIALIZAÇÃO:
                        // 1. Se temos um ID selecionado manualmente, usa ele.
                        // 2. Se não, usa o modo "environment" (traseira) padrão.
                        let cameraConfig = this.selectedCameraId 
                            ? { deviceId: { exact: this.selectedCameraId } }
                            : { facingMode: "environment" };

                        await this.html5QrCode.start(
                            cameraConfig, 
                            config, 
                            (text) => {
                                this.stopAndProcess(text);
                            },
                            (err) => { /* Ignora erros de frame vazio */ }
                        );
                    } catch (err) {
                        console.error("Erro ao iniciar câmera:", err);
                        // Fallback: Se falhar (ex: environment não suportado), tenta qualquer câmera
                        if (!this.selectedCameraId) {
                            this.html5QrCode.start({ facingMode: "user" }, config, (text) => this.stopAndProcess(text))
                                .catch(e => alert("Não foi possível acessar a câmera. Verifique as permissões."));
                        }
                    }
                },

                async stopAndProcess(text) {
                    if(this.html5QrCode) {
                        await this.html5QrCode.stop();
                        this.html5QrCode.clear();
                    }
                    this.$wire.handleBarcodeScan(text);
                },

                async cycleCamera() {
                    // 1. Para o scanner atual
                    if (this.html5QrCode) {
                        try { await this.html5QrCode.stop(); } catch(e) {}
                    }

                    // 2. Lista câmeras se ainda não listou
                    if (this.allCameras.length === 0) {
                        try {
                            this.allCameras = await Html5Qrcode.getCameras();
                        } catch(e) {
                            alert("Erro ao listar câmeras.");
                            return;
                        }
                    }

                    if (this.allCameras.length < 2) {
                        alert("Apenas uma câmera detectada.");
                        this.startScanner();
                        return;
                    }

                    // 3. Encontra o índice da câmera atual (ou define 0)
                    let currentIndex = 0;
                    if (this.selectedCameraId) {
                        currentIndex = this.allCameras.findIndex(c => c.id === this.selectedCameraId);
                    }

                    // 4. Pega a próxima câmera
                    let nextIndex = (currentIndex + 1) % this.allCameras.length;
                    this.selectedCameraId = this.allCameras[nextIndex].id;

                    // 5. Reinicia com o novo ID
                    this.startScanner();
                },

                // Lógica de Foto e Crop
                triggerInput() {
                    document.getElementById('manual-input').click();
                },

                openCrop(imgSrc) {
                    const overlay = document.getElementById('crop-overlay');
                    const img = document.getElementById('image-to-crop');
                    
                    img.src = imgSrc;
                    overlay.style.display = 'flex';

                    if (this.cropper) this.cropper.destroy();
                    
                    this.cropper = new Cropper(img, {
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.9,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        background: false
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
                    
                    const canvas = this.cropper.getCroppedCanvas({ maxWidth: 1280, maxHeight: 1280 });
                    const base64 = canvas.toDataURL('image/jpeg', 0.85);

                    await this.$wire.processCroppedImage(base64);
                    
                    this.status = 'success';
                    this.cancelCrop();
                },

                reset() {
                    this.$wire.resetScanner();
                },

                save() {
                    this.$wire.save();
                }
            }
        }
    </script>
</x-filament-panels::page>