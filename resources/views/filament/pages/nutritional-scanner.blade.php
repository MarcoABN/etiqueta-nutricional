<x-filament-panels::page>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; color: white; }
        .app-container { height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 10; }
        
        #scanner-view { position: absolute; inset: 0; z-index: 20; background: #000; }
        #reader { width: 100%; height: 100%; object-fit: cover; }
        
        .btn-switch-camera {
            position: absolute; top: 25px; right: 25px; z-index: 50;
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(8px);
            display: flex; align-items: center; justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.3); color: white;
        }

        .scan-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 260px; height: 160px; pointer-events: none; z-index: 30; box-shadow: 0 0 0 9999px rgba(0,0,0,0.75); border-radius: 20px; border: 2px solid rgba(255,255,255,0.4); }
        .scan-line { position: absolute; width: 100%; height: 2px; background: #22c55e; box-shadow: 0 0 15px #22c55e; animation: scanning 2s infinite ease-in-out; }
        @keyframes scanning { 0% {top: 10%;} 50% {top: 90%;} 100% {top: 10%;} }

        #photo-view { position: absolute; inset: 0; z-index: 40; background: #111; display: flex; flex-direction: column; }
        .product-info { background: #1f2937; padding: 20px; border-bottom: 1px solid #374151; }
        .btn-capture { background: #22c55e; color: #000; padding: 18px; border-radius: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 10px; width: 85%; margin: 0 auto; }
        .footer-actions { padding: 15px; background: #000; display: flex; gap: 10px; }
        .btn-save { background: #22c55e; flex: 2; height: 55px; border-radius: 12px; color: #000; font-weight: bold; }
        .btn-save:disabled { background: #1a5e32; opacity: 0.5; color: #444; }
        .btn-cancel { background: #374151; flex: 1; height: 55px; border-radius: 12px; color: #fff; }

        /* Estilos do Modal de Crop */
        #crop-overlay { position: fixed; inset: 0; background: #000; z-index: 100; display: none; flex-direction: column; }
        .crop-area { flex: 1; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        #image-to-crop { max-width: 100%; max-height: 100%; }
    </style>

    <div class="app-container" x-data="scannerApp()">
        <div class="hidden-uploader" wire:ignore>
            {{ $this->form }}
        </div>

        <template x-if="!$wire.foundProduct">
            <button @click="switchCamera()" class="btn-switch-camera">
                <x-heroicon-o-arrow-path class="w-6 h-6" />
            </button>
        </template>

        <div id="scanner-view" :class="$wire.foundProduct ? 'hidden' : ''">
            <div id="reader" wire:ignore></div>
            <div class="scan-frame"><div class="scan-line"></div></div>
        </div>

        <template x-if="$wire.foundProduct">
            <div id="photo-view">
                <div class="product-info">
                    <span class="text-green-500 text-[10px] font-bold tracking-widest uppercase">EAN: <span x-text="$wire.scannedCode"></span></span>
                    <h2 class="text-lg font-bold leading-tight" x-text="$wire.foundProduct.product_name"></h2>
                </div>

                <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-6">
                        <x-heroicon-o-camera x-show="status === 'idle'" class="w-10 h-10 text-gray-500" />
                        <svg x-show="status === 'loading'" class="animate-spin h-10 w-10 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <x-heroicon-s-check-circle x-show="status === 'success'" class="w-10 h-10 text-green-500" />
                    </div>

                    <button type="button" @click="openCamera()" class="btn-capture shadow-lg active:scale-95 transition-all">
                        <x-heroicon-s-camera class="w-6 h-6" />
                        TIRAR FOTO
                    </button>
                </div>

                <div class="footer-actions">
                    <button @click="$wire.resetScanner()" class="btn-cancel">VOLTAR</button>
                    <button id="btn-submit" @click="$wire.save()" :disabled="status !== 'success'" class="btn-save">SALVAR</button>
                </div>
            </div>
        </template>

        <div id="crop-overlay">
            <div class="crop-area">
                <img id="image-to-crop" src="">
            </div>
            <div class="footer-actions">
                <button @click="closeCrop()" class="btn-cancel">CANCELAR</button>
                <button @click="confirmCrop()" class="btn-save">CONFIRMAR RECORTE</button>
            </div>
        </div>
    </div>

    <script>
        function scannerApp() {
            return {
                status: 'idle', // idle, loading, success
                html5QrCode: null,
                cropper: null,
                currentCameraId: null,
                backCameras: [],

                init() {
                    this.startScanner();
                    
                    Livewire.on('reset-scanner', () => {
                        this.status = 'idle';
                        setTimeout(() => this.startScanner(), 600);
                    });
                },

                async startScanner() {
                    if (this.$wire.foundProduct) return;
                    
                    if (this.html5QrCode) {
                        try { await this.html5QrCode.stop(); } catch(e) {}
                    }

                    const devices = await Html5Qrcode.getCameras();
                    this.backCameras = devices.filter(d => !d.label.toLowerCase().includes('front'));
                    if (this.backCameras.length === 0) this.backCameras = devices;
                    
                    if (!this.currentCameraId) this.currentCameraId = this.backCameras[0].id;

                    this.html5QrCode = new Html5Qrcode("reader");
                    this.html5QrCode.start(this.currentCameraId, { fps: 15, qrbox: { width: 250, height: 150 } }, (decodedText) => {
                        this.html5QrCode.stop().then(() => {
                            this.$wire.handleBarcodeScan(decodedText);
                        });
                    }).catch(() => {});
                },

                async switchCamera() {
                    let index = this.backCameras.findIndex(c => c.id === this.currentCameraId);
                    this.currentCameraId = this.backCameras[(index + 1) % this.backCameras.length].id;
                    this.startScanner();
                },

                openCamera() {
                    // Localiza o input de arquivo dentro do componente Filament via ID fixo
                    const container = document.getElementById('hidden-file-input');
                    const fileInput = container ? container.querySelector('input[type="file"]') : null;

                    if (fileInput) {
                        fileInput.setAttribute('capture', 'environment');
                        
                        fileInput.onchange = (e) => {
                            const file = e.target.files[0];
                            if (file) {
                                const reader = new FileReader();
                                reader.onload = (event) => this.initCrop(event.target.result);
                                reader.readAsDataURL(file);
                            }
                        };
                        fileInput.click();
                    } else {
                        alert('Erro ao acessar a câmera. Tente recarregar a página.');
                    }
                },

                initCrop(imageSrc) {
                    const overlay = document.getElementById('crop-overlay');
                    const img = document.getElementById('image-to-crop');
                    
                    img.src = imageSrc;
                    overlay.style.display = 'flex';

                    if (this.cropper) this.cropper.destroy();

                    this.cropper = new Cropper(img, {
                        viewMode: 1,
                        autoCropArea: 0.8,
                        background: false,
                        responsive: true,
                        restore: false,
                    });
                },

                closeCrop() {
                    document.getElementById('crop-overlay').style.display = 'none';
                    if (this.cropper) this.cropper.destroy();
                },

                async confirmCrop() {
                    this.status = 'loading';
                    const canvas = this.cropper.getCroppedCanvas({ maxWidth: 1280, maxHeight: 1280 });
                    const base64 = canvas.toDataURL('image/jpeg', 0.85);
                    
                    await this.$wire.processCroppedImage(base64);
                    
                    this.closeCrop();
                    this.status = 'success';
                }
            }
        }
    </script>
</x-filament-panels::page>