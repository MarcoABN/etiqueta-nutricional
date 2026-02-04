<x-filament-panels::page>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* Estilos originais preservados */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; color: white; }
        .app-container { height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 10; }
        .hidden-uploader { position: absolute; opacity: 0; pointer-events: none; width: 1px; height: 1px; }

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

        /* Estilos Novos para o Crop */
        #crop-ui { position: fixed; inset: 0; background: #000; z-index: 100; display: none; flex-direction: column; }
        .crop-container { flex: 1; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        #image-to-crop { max-width: 100%; max-height: 100%; display: block; }
    </style>

    <div class="app-container">
        <div class="hidden-uploader" wire:ignore>{{ $this->form }}</div>

        @if(!$foundProduct)
        <button id="btn-switch" class="btn-switch-camera active:scale-90 transition-transform">
            <x-heroicon-o-arrow-path class="w-6 h-6" />
        </button>
        @endif

        <div id="scanner-view" class="{{ $foundProduct ? 'hidden' : '' }}">
            <div id="reader" wire:ignore></div>
            <div class="scan-frame"><div class="scan-line"></div></div>
        </div>

        @if($foundProduct)
            <div id="photo-view">
                <div class="product-info">
                    <span class="text-green-500 text-[10px] font-bold tracking-widest uppercase">EAN: {{ $scannedCode }}</span>
                    <h2 class="text-lg font-bold leading-tight">{{ $foundProduct->product_name }}</h2>
                </div>

                <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-6">
                        <x-heroicon-o-camera id="icon-idle" class="w-10 h-10 text-gray-500" />
                        <svg id="icon-loading" class="animate-spin h-10 w-10 text-green-500 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <x-heroicon-s-check-circle id="icon-success" class="w-10 h-10 text-green-500 hidden" />
                    </div>

                    <button type="button" onclick="triggerCamera()" class="btn-capture shadow-lg active:scale-95 transition-all">
                        <x-heroicon-s-camera class="w-6 h-6" />
                        TIRAR FOTO
                    </button>
                </div>

                <div class="footer-actions">
                    <button wire:click="resetScanner" class="btn-cancel">VOLTAR</button>
                    <button id="btn-submit" wire:click="save" disabled class="btn-save">SALVAR</button>
                </div>
            </div>
        @endif

        <div id="crop-ui">
            <div class="crop-container">
                <img id="image-to-crop" src="">
            </div>
            <div class="footer-actions">
                <button onclick="cancelCrop()" class="btn-cancel">CANCELAR</button>
                <button onclick="applyCrop()" class="btn-save">RECORTAR</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;
            let currentCameraId = null;
            let backCameras = [];
            let cropper = null;

            async function loadCameras() {
                try {
                    const devices = await Html5Qrcode.getCameras();
                    backCameras = devices.filter(d => !d.label.toLowerCase().includes('front') && !d.label.toLowerCase().includes('user'));
                    if (backCameras.length === 0) backCameras = devices;
                    currentCameraId = backCameras[0].id;
                } catch (e) { console.error(e); }
            }

            window.triggerCamera = function() {
                const fileInput = document.querySelector('.hidden-uploader input[type="file"]');
                if (fileInput) { 
                    fileInput.setAttribute('capture', 'environment');
                    fileInput.setAttribute('accept', 'image/*');
                    
                    // Hook para capturar o arquivo antes do upload automático
                    fileInput.onchange = (e) => {
                        const file = e.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = (event) => startCropping(event.target.result);
                            reader.readAsDataURL(file);
                        }
                    };
                    fileInput.click(); 
                }
            };

            function startCropping(imgSrc) {
                const cropUi = document.getElementById('crop-ui');
                const cropImg = document.getElementById('image-to-crop');
                
                cropImg.src = imgSrc;
                cropUi.style.display = 'flex';

                if (cropper) cropper.destroy();

                cropper = new Cropper(cropImg, {
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.9,
                    restore: false,
                    guides: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            }

            window.cancelCrop = () => {
                document.getElementById('crop-ui').style.display = 'none';
                if (cropper) cropper.destroy();
            };

            window.applyCrop = () => {
                setUIStatus('loading');
                const canvas = cropper.getCroppedCanvas({ maxWidth: 1600, maxHeight: 1600 });
                const base64 = canvas.toDataURL('image/jpeg', 0.85);
                
                // Envia a imagem recortada para o servidor via Livewire
                @this.processCroppedImage(base64).then(() => {
                    document.getElementById('crop-ui').style.display = 'none';
                    setUIStatus('success');
                });
            };

            function setUIStatus(status) {
                const btn = document.getElementById('btn-submit');
                const idle = document.getElementById('icon-idle');
                const load = document.getElementById('icon-loading');
                const success = document.getElementById('icon-success');
                if (!btn) return;
                [idle, load, success].forEach(i => i?.classList.add('hidden'));
                if (status === 'loading') { btn.disabled = true; load?.classList.remove('hidden'); }
                else if (status === 'success') { btn.disabled = false; success?.classList.remove('hidden'); }
                else { btn.disabled = true; idle?.classList.remove('hidden'); }
            }

            async function startScanner() {
                if (@json($foundProduct)) return;
                if (html5QrCode) { try { await html5QrCode.stop(); } catch(e) {} html5QrCode = null; }
                if (backCameras.length === 0) await loadCameras();
                
                html5QrCode = new Html5Qrcode("reader");
                html5QrCode.start(currentCameraId, { fps: 15, qrbox: { width: 250, height: 150 } }, (decodedText) => {
                    html5QrCode.stop().then(() => { @this.handleBarcodeScan(decodedText); });
                }).catch(() => {});
            }

            document.getElementById('btn-switch')?.addEventListener('click', async () => {
                let index = backCameras.findIndex(c => c.id === currentCameraId);
                currentCameraId = backCameras[(index + 1) % backCameras.length].id;
                await startScanner();
            });

            startScanner();
            Livewire.on('reset-scanner', () => { setUIStatus('idle'); setTimeout(startScanner, 600); });
        });
    </script>
</x-filament-panels::page>