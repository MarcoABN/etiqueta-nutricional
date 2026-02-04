<x-filament-panels::page>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; color: white; }
        .app-container { height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 10; }
        
        #scanner-view { position: absolute; inset: 0; z-index: 20; background: #000; overflow: hidden; }
        #reader { width: 100%; height: 100%; }
        #reader video { object-fit: cover !important; width: 100% !important; height: 100% !important; }
        
        .btn-switch-camera {
            position: absolute; top: 25px; right: 25px; z-index: 60;
            width: 50px; height: 50px; border-radius: 50%;
            background: rgba(0, 0, 0, 0.6); display: flex; align-items: center; justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.3); color: white;
        }

        .scan-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 280px; height: 180px; pointer-events: none; z-index: 30; box-shadow: 0 0 0 9999px rgba(0,0,0,0.7); border-radius: 20px; border: 3px solid rgba(34, 197, 94, 0.5); }
        .scan-line { position: absolute; width: 100%; height: 2px; background: #22c55e; box-shadow: 0 0 15px #22c55e; animation: scanning 2s infinite ease-in-out; }
        @keyframes scanning { 0% {top: 10%;} 50% {top: 90%;} 100% {top: 10%;} }

        #photo-view { position: absolute; inset: 0; z-index: 40; background: #111; display: flex; flex-direction: column; }
        .product-info { background: #1f2937; padding: 25px 20px; border-bottom: 1px solid #374151; }
        .btn-capture { background: #22c55e; color: #000; padding: 20px; border-radius: 16px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 12px; width: 85%; margin: 0 auto; }
        .footer-actions { padding: 20px; background: #000; display: flex; gap: 12px; }
        .btn-save { background: #22c55e; flex: 2; height: 60px; border-radius: 14px; color: #000; font-weight: bold; font-size: 16px; }
        .btn-save:disabled { background: #1a5e32; opacity: 0.5; }
        .btn-cancel { background: #374151; flex: 1; height: 60px; border-radius: 14px; color: #fff; }

        #crop-overlay { position: fixed; inset: 0; background: #000; z-index: 100; display: none; flex-direction: column; }
        .crop-area { flex: 1; position: relative; display: flex; align-items: center; justify-content: center; background: #000; }
        #image-to-crop { max-width: 100%; max-height: 100%; display: block; }
    </style>

    <div class="app-container" x-data="scannerApp()">
        <div class="hidden-uploader" wire:ignore>
            {{ $this->form }}
        </div>

        <button x-show="!$wire.foundProduct" @click="switchCamera()" class="btn-switch-camera" type="button">
            <x-heroicon-o-arrow-path class="w-7 h-7" />
        </button>

        <div id="scanner-view" x-show="!$wire.foundProduct">
            <div id="reader" wire:ignore></div>
            <div class="scan-frame"><div class="scan-line"></div></div>
        </div>

        <template x-if="$wire.foundProduct">
            <div id="photo-view">
                <div class="product-info">
                    <span class="text-green-500 text-xs font-bold tracking-widest uppercase">EAN: <span x-text="$wire.scannedCode"></span></span>
                    <h2 class="text-xl font-bold mt-1" x-text="$wire.foundProduct.product_name"></h2>
                </div>

                <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mb-8">
                        <x-heroicon-o-camera x-show="status === 'idle'" class="w-12 h-12 text-gray-500" />
                        <svg x-show="status === 'loading'" class="animate-spin h-12 w-12 text-green-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <x-heroicon-s-check-circle x-show="status === 'success'" class="w-12 h-12 text-green-500" />
                    </div>

                    <button type="button" @click="triggerCamera()" class="btn-capture shadow-xl active:scale-95 transition-all">
                        <x-heroicon-s-camera class="w-7 h-7" />
                        TIRAR FOTO DA TABELA
                    </button>
                    <p class="text-gray-500 mt-4 text-sm" x-show="status === 'idle'">A foto deve focar na tabela nutricional</p>
                </div>

                <div class="footer-actions">
                    <button type="button" @click="$wire.resetScanner()" class="btn-cancel">VOLTAR</button>
                    <button type="button" @click="$wire.save()" :disabled="status !== 'success'" class="btn-save">SALVAR</button>
                </div>
            </div>
        </template>

        <div id="crop-overlay">
            <div class="crop-area">
                <img id="image-to-crop" src="">
            </div>
            <div class="footer-actions">
                <button type="button" @click="closeCrop()" class="btn-cancel">CANCELAR</button>
                <button type="button" @click="confirmCrop()" class="btn-save">CONCLUIR RECORTE</button>
            </div>
        </div>
    </div>

    <script>
        function scannerApp() {
            return {
                status: 'idle',
                html5QrCode: null,
                cropper: null,
                currentCameraId: null,
                cameras: [],

                async init() {
                    await this.loadCameras();
                    this.startScanner();
                    
                    Livewire.on('reset-scanner', () => {
                        this.status = 'idle';
                        this.startScanner();
                    });
                },

                async loadCameras() {
                    try {
                        const devices = await Html5Qrcode.getCameras();
                        // Prioriza câmeras traseiras
                        this.cameras = devices.filter(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('traseira') || d.label.toLowerCase().includes('rear'));
                        if (this.cameras.length === 0) this.cameras = devices;
                        this.currentCameraId = this.cameras[0].id;
                    } catch (e) { console.error("Erro câmeras:", e); }
                },

                async startScanner() {
                    if (this.$wire.foundProduct) return;
                    
                    if (this.html5QrCode) {
                        await this.html5QrCode.stop();
                        this.html5QrCode = null;
                    }

                    this.html5QrCode = new Html5Qrcode("reader");
                    const config = { 
                        fps: 20, 
                        qrbox: { width: 250, height: 180 },
                        aspectRatio: 1.0 // Quadrado ajuda no foco de alguns aparelhos
                    };

                    this.html5QrCode.start(
                        this.currentCameraId, 
                        config, 
                        (decodedText) => {
                            this.html5QrCode.stop().then(() => {
                                this.$wire.handleBarcodeScan(decodedText);
                            });
                        }
                    ).catch(err => console.error("Erro ao iniciar:", err));
                },

                async switchCamera() {
                    if (this.cameras.length < 2) return;
                    let currentIndex = this.cameras.findIndex(c => c.id === this.currentCameraId);
                    this.currentCameraId = this.cameras[(currentIndex + 1) % this.cameras.length].id;
                    await this.startScanner();
                },

                triggerCamera() {
                    // Busca o input dentro do container do Filament
                    const input = document.querySelector('#hidden-file-input input[type="file"]');
                    if (input) {
                        input.onchange = (e) => {
                            const file = e.target.files[0];
                            if (file) {
                                const reader = new FileReader();
                                reader.onload = (ev) => this.openCrop(ev.target.result);
                                reader.readAsDataURL(file);
                            }
                        };
                        input.click();
                    }
                },

                openCrop(src) {
                    const overlay = document.getElementById('crop-overlay');
                    const img = document.getElementById('image-to-crop');
                    img.src = src;
                    overlay.style.display = 'flex';

                    if (this.cropper) this.cropper.destroy();
                    this.cropper = new Cropper(img, {
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.8,
                        background: false,
                        responsive: true,
                    });
                },

                closeCrop() {
                    document.getElementById('crop-overlay').style.display = 'none';
                    if (this.cropper) this.cropper.destroy();
                },

                async confirmCrop() {
                    this.status = 'loading';
                    const canvas = this.cropper.getCroppedCanvas({ maxWidth: 1600, maxHeight: 1600 });
                    const base64 = canvas.toDataURL('image/jpeg', 0.8);
                    
                    await this.$wire.processCroppedImage(base64);
                    this.closeCrop();
                    this.status = 'success';
                }
            }
        }
    </script>
</x-filament-panels::page>