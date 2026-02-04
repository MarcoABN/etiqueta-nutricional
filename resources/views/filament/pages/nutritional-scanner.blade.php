<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* Layout Fullscreen Mobile */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; }

        .app-container {
            height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; background: #000;
            display: flex; flex-direction: column; z-index: 10;
        }

        /* Área do formulário Filament (Escondida mas ativa) */
        .hidden-uploader { 
            position: absolute; opacity: 0; pointer-events: none; width: 1px; height: 1px; 
        }

        /* Scanner */
        #scanner-view { position: absolute; inset: 0; z-index: 20; background: #000; }
        #reader { width: 100%; height: 100%; object-fit: cover; }
        
        .scan-frame {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 280px; height: 180px; pointer-events: none; z-index: 30;
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.7); border-radius: 15px;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .scan-line {
            position: absolute; width: 100%; height: 2px; background: #22c55e;
            box-shadow: 0 0 15px #22c55e; animation: scanning 2s infinite ease-in-out;
        }
        @keyframes scanning { 0% {top: 10%;} 50% {top: 90%;} 100% {top: 10%;} }

        /* Tela de Captura */
        #photo-view { 
            position: absolute; inset: 0; z-index: 40; background: #111; 
            display: flex; flex-direction: column; color: white;
        }
        .product-info { background: #1f2937; padding: 20px; border-bottom: 1px solid #374151; }
        .btn-capture {
            background: #22c55e; color: #000; padding: 18px; border-radius: 14px;
            font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 80%; margin: 0 auto;
        }
        .footer-actions { padding: 15px; background: #000; display: flex; gap: 10px; }
        .btn-save { background: #22c55e; flex: 2; height: 55px; border-radius: 12px; color: #000; font-weight: bold; }
        .btn-cancel { background: #374151; flex: 1; height: 55px; border-radius: 12px; color: #fff; }
        
        /* Estado desabilitado */
        .btn-save:disabled { background: #1a5e32 !important; color: #444 !important; opacity: 0.6; }
    </style>

    <div class="app-container">
        
        {{-- Componente FileUpload nativo do Filament escondido --}}
        <div class="hidden-uploader" wire:ignore>
            {{ $this->form }}
        </div>

        {{-- PASSO 1: LEITURA --}}
        <div id="scanner-view" class="{{ $foundProduct ? 'hidden' : '' }}">
            <div id="reader"></div>
            <div class="scan-frame"><div class="scan-line"></div></div>
            <div class="absolute bottom-10 w-full text-center text-white/70 px-4">Aproxime o código de barras</div>
        </div>

        {{-- PASSO 2: FOTO E CONFIRMAÇÃO --}}
        @if($foundProduct)
            <div id="photo-view">
                <div class="product-info">
                    <span class="text-green-500 text-[10px] font-bold tracking-widest uppercase">Produto Identificado</span>
                    <h2 class="text-xl font-bold">{{ $foundProduct->product_name }}</h2>
                    <p class="text-gray-400 text-xs font-mono">{{ $scannedCode }}</p>
                </div>

                <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <div id="upload-icon-container" class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-4">
                        <x-heroicon-o-camera id="icon-idle" class="w-10 h-10 text-gray-500" />
                        <svg id="icon-loading" class="animate-spin h-10 w-10 text-green-500 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <x-heroicon-s-check-circle id="icon-success" class="w-10 h-10 text-green-500 hidden" />
                    </div>

                    <h3 id="status-title" class="text-lg font-bold">Tabela Nutricional</h3>
                    <p id="status-text" class="text-gray-400 text-sm mb-8">Clique abaixo para capturar a foto</p>

                    <button type="button" onclick="triggerCamera()" class="btn-capture active:scale-95 transition-all">
                        <x-heroicon-s-camera class="w-6 h-6" />
                        TIRAR FOTO
                    </button>
                </div>

                <div class="footer-actions">
                    <button wire:click="resetScanner" class="btn-cancel">VOLTAR</button>
                    <button id="btn-submit" wire:click="save" disabled class="btn-save">
                        <span wire:loading.remove wire:target="save">SALVAR</span>
                        <span wire:loading wire:target="save">SALVANDO...</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;

            // Função para clicar no input real
            window.triggerCamera = function() {
                const fileInput = document.querySelector('.hidden-uploader input[type="file"]');
                if (fileInput) fileInput.click();
            };

            // Escuta eventos do FilePond para saber o status do upload
            function listenToUpload() {
                document.addEventListener('FilePond:addfile', () => {
                    setUIStatus('loading');
                });

                document.addEventListener('FilePond:processfile', (e) => {
                    if (!e.detail.error) setUIStatus('success');
                    else setUIStatus('error');
                });

                document.addEventListener('FilePond:removefile', () => {
                    setUIStatus('idle');
                });
            }

            function setUIStatus(status) {
                const btn = document.getElementById('btn-submit');
                const title = document.getElementById('status-title');
                const text = document.getElementById('status-text');
                const iconIdle = document.getElementById('icon-idle');
                const iconLoad = document.getElementById('icon-loading');
                const iconSuccess = document.getElementById('icon-success');

                if (!btn) return;

                // Reset icons
                [iconIdle, iconLoad, iconSuccess].forEach(i => i?.classList.add('hidden'));

                if (status === 'loading') {
                    btn.disabled = true;
                    iconLoad.classList.remove('hidden');
                    title.innerText = "Processando...";
                    text.innerText = "Aguarde o envio da imagem";
                } else if (status === 'success') {
                    btn.disabled = false;
                    iconSuccess.classList.remove('hidden');
                    title.innerText = "Imagem Pronta!";
                    text.innerText = "Tudo certo, pode salvar agora";
                } else if (status === 'idle') {
                    btn.disabled = true;
                    iconIdle.classList.remove('hidden');
                    title.innerText = "Tabela Nutricional";
                    text.innerText = "Clique abaixo para capturar a foto";
                }
            }

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
            listenToUpload();

            Livewire.on('reset-scanner', () => {
                setTimeout(startScanner, 400);
            });
        });
    </script>
</x-filament-panels::page>