<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; color: white; }
        .app-container { height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 10; }
        .hidden-uploader { position: absolute; opacity: 0; pointer-events: none; width: 1px; height: 1px; }

        /* Scanner */
        #scanner-view { position: absolute; inset: 0; z-index: 20; background: #000; }
        #reader { width: 100%; height: 100%; object-fit: cover; }
        .scan-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 280px; height: 180px; pointer-events: none; z-index: 30; box-shadow: 0 0 0 9999px rgba(0,0,0,0.7); border-radius: 15px; border: 2px solid rgba(255,255,255,0.3); }
        .scan-line { position: absolute; width: 100%; height: 2px; background: #22c55e; box-shadow: 0 0 15px #22c55e; animation: scanning 2s infinite ease-in-out; }
        @keyframes scanning { 0% {top: 10%;} 50% {top: 90%;} 100% {top: 10%;} }

        /* Photo View */
        #photo-view { position: absolute; inset: 0; z-index: 40; background: #111; display: flex; flex-direction: column; }
        .product-info { background: #1f2937; padding: 20px; border-bottom: 1px solid #374151; }
        .btn-capture { background: #22c55e; color: #000; padding: 18px; border-radius: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 10px; width: 85%; margin: 0 auto; }
        .footer-actions { padding: 15px; background: #000; display: flex; gap: 10px; }
        .btn-save { background: #22c55e; flex: 2; height: 55px; border-radius: 12px; color: #000; font-weight: bold; }
        .btn-save:disabled { background: #1a5e32; opacity: 0.5; color: #444; }
        .btn-cancel { background: #374151; flex: 1; height: 55px; border-radius: 12px; color: #fff; }
        
        /* Preview da imagem antiga se existir */
        .old-photo-preview { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 2px solid #374151; }
    </style>

    <div class="app-container">
        <div class="hidden-uploader" wire:ignore>
            {{ $this->form }}
        </div>

        <div id="scanner-view" class="{{ $foundProduct ? 'hidden' : '' }}">
            <div id="reader"></div>
            <div class="scan-frame"><div class="scan-line"></div></div>
        </div>

        @if($foundProduct)
            <div id="photo-view">
                <div class="product-info flex justify-between items-center">
                    <div>
                        <span class="text-green-500 text-[10px] font-bold tracking-widest uppercase">EAN: {{ $scannedCode }}</span>
                        <h2 class="text-lg font-bold leading-tight">{{ $foundProduct->product_name }}</h2>
                    </div>
                    @if($foundProduct->image_nutritional)
                        <img src="{{ Storage::disk('public')->url($foundProduct->image_nutritional) }}" class="old-photo-preview">
                    @endif
                </div>

                <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-4">
                        <x-heroicon-o-camera id="icon-idle" class="w-10 h-10 text-gray-500" />
                        <svg id="icon-loading" class="animate-spin h-10 w-10 text-green-500 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <x-heroicon-s-check-circle id="icon-success" class="w-10 h-10 text-green-500 hidden" />
                    </div>

                    <h3 id="status-title" class="text-lg font-bold">Atualizar Foto</h3>
                    <p id="status-text" class="text-gray-400 text-sm mb-8">A foto anterior será substituída</p>

                    <button type="button" onclick="triggerCamera()" class="btn-capture shadow-lg active:scale-95 transition-all">
                        <x-heroicon-s-camera class="w-6 h-6" />
                        CAPTURAR NOVA
                    </button>
                </div>

                <div class="footer-actions">
                    <button wire:click="resetScanner" class="btn-cancel">VOLTAR</button>
                    <button id="btn-submit" wire:click="save" disabled class="btn-save">
                        SALVAR ALTERAÇÃO
                    </button>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;

            window.triggerCamera = function() {
                const fileInput = document.querySelector('.hidden-uploader input[type="file"]');
                if (fileInput) {
                    fileInput.click();
                    setUIStatus('loading');
                }
            };

            function setUIStatus(status) {
                const btn = document.getElementById('btn-submit');
                if (!btn) return;
                const title = document.getElementById('status-title');
                const text = document.getElementById('status-text');
                const iconIdle = document.getElementById('icon-idle');
                const iconLoad = document.getElementById('icon-loading');
                const iconSuccess = document.getElementById('icon-success');

                [iconIdle, iconLoad, iconSuccess].forEach(i => i?.classList.add('hidden'));

                if (status === 'loading') {
                    btn.disabled = true;
                    iconLoad.classList.remove('hidden');
                    title.innerText = "Processando...";
                } else if (status === 'success') {
                    btn.disabled = false;
                    iconSuccess.classList.remove('hidden');
                    title.innerText = "Pronta para Salvar!";
                    text.innerText = "A nova imagem foi processada.";
                } else {
                    btn.disabled = true;
                    iconIdle.classList.remove('hidden');
                }
            }

            // ESCUTA TRÍPLICE PARA GARANTIR
            // 1. Evento do FilePond (Nativo)
            window.addEventListener('FilePond:processfile', (e) => {
                if (!e.detail.error) setUIStatus('success');
            });

            // 2. Callback do Livewire (Após o back receber o arquivo)
            Livewire.on('file-uploaded-callback', () => {
                setUIStatus('success');
            });

            // 3. Fallback manual (Verifica se o componente do Filament preencheu o campo)
            setInterval(() => {
                const btn = document.getElementById('btn-submit');
                if (btn && btn.disabled) {
                    // Se o Livewire já tem o dado mas a UI não habilitou, habilitamos
                    if (@this.get('data.image_nutritional')) {
                        setUIStatus('success');
                    }
                }
            }, 1000);

            function startScanner() {
                if (@json($foundProduct)) return;
                html5QrCode = new Html5Qrcode("reader");
                html5QrCode.start({ facingMode: "environment" }, { fps: 15, qrbox: { width: 250, height: 150 } },
                    (decodedText) => {
                        html5QrCode.stop().then(() => { @this.handleBarcodeScan(decodedText); });
                    }
                ).catch(() => {});
            }

            startScanner();
            Livewire.on('reset-scanner', () => {
                setTimeout(startScanner, 400);
                setUIStatus('idle');
            });
        });
    </script>
</x-filament-panels::page>