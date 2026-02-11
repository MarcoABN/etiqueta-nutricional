<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    {{-- Som de Beep --}}
    <audio id="scan-sound" src="{{ asset('sounds/beep.mp3') }}" preload="auto"></audio>

    {{-- ESTILOS DE QUIOSQUE (Igual ao Nutricional) --}}
    <style>
        /* Remove toda a interface do Filament (Menu, Topbar, Footer) */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        
        /* Define o container principal como tela cheia preta */
        .kiosk-container { 
            position: fixed; top: 0; left: 0; width: 100vw; height: 100dvh; 
            background: #000; color: white; z-index: 9999; display: flex; flex-direction: column; 
        }

        /* Área do Vídeo da Câmera */
        #scanner-viewport { flex: 1; position: relative; overflow: hidden; background: #000; }
        #reader { width: 100%; height: 100%; object-fit: cover; }
        #reader video { object-fit: cover; width: 100%; height: 100%; }

        /* Overlay de Edição (Modal) */
        #edit-modal { 
            position: absolute; bottom: 0; left: 0; width: 100%; 
            background: rgba(255, 255, 255, 0.95); color: #000; 
            border-top-left-radius: 20px; border-top-right-radius: 20px;
            padding: 20px; box-shadow: 0 -5px 20px rgba(0,0,0,0.5);
            transform: translateY(100%); transition: transform 0.3s ease-out;
            z-index: 50; max-height: 80vh; overflow-y: auto;
        }
        #edit-modal.open { transform: translateY(0); }

        /* Botão Flutuante Trocar Câmera */
        .fab-camera {
            position: absolute; top: 20px; right: 20px; z-index: 40;
            background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.3);
            color: white; border-radius: 50%; width: 50px; height: 50px;
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(5px);
        }
    </style>

    <div class="kiosk-container" x-data="scannerLogic()" x-init="initApp()">
        
        {{-- TELA 1: SELEÇÃO DE FILIAL (Só aparece se não tiver filial) --}}
        @if(!$filialId)
            <div class="flex-1 flex flex-col items-center justify-center p-6 bg-gray-900">
                <div class="w-full max-w-md bg-white rounded-xl p-6 shadow-2xl">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Selecionar Filial</h2>
                    {{ $this->form }}
                </div>
            </div>
        @else
            {{-- TELA 2: SCANNER ATIVO --}}
            
            {{-- Header Transparente --}}
            <div class="absolute top-0 left-0 w-full p-4 flex justify-between items-start z-30 pointer-events-none">
                <div class="pointer-events-auto bg-black/50 px-3 py-1 rounded backdrop-blur-sm">
                    <span class="text-xs text-gray-300 block">FILIAL</span>
                    <span class="text-xl font-bold text-white font-mono">{{ $filialId }}</span>
                </div>
                <button wire:click="changeFilial" class="pointer-events-auto bg-red-600/80 text-white text-xs font-bold px-3 py-2 rounded hover:bg-red-600">
                    SAIR
                </button>
            </div>

            {{-- Botão Trocar Câmera --}}
            <button @click="switchCamera" class="fab-camera pointer-events-auto" x-show="cameras.length > 1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>

            {{-- Viewport da Câmera --}}
            <div id="scanner-viewport">
                <div id="reader"></div>
                
                {{-- Mira visual (Overlay CSS) --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-50">
                    <div class="w-64 h-40 border-2 border-white/50 rounded-lg"></div>
                    <div class="absolute w-full h-0.5 bg-red-500/50 top-1/2"></div>
                </div>
            </div>

            {{-- MODAL DE EDIÇÃO (Desliza de baixo para cima) --}}
            <div id="edit-modal" :class="{ 'open': isEditing }">
                @if($product)
                    <div class="flex flex-col gap-4">
                        {{-- Info do Produto --}}
                        <div class="border-b pb-2">
                            <div class="flex justify-between text-xs text-gray-500 font-mono">
                                <span>COD: {{ $product->CODPROD }}</span>
                                <span>EAN: {{ $product->CODAUXILIAR }}</span>
                            </div>
                            <h3 class="text-lg font-bold leading-tight mt-1 text-gray-900">{{ $product->DESCRICAO }}</h3>
                        </div>

                        {{-- Comparativo de Preços --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-gray-100 p-2 rounded">
                                <span class="text-xs text-gray-500 block">Custo</span>
                                <span class="text-lg font-bold text-gray-700">R$ {{ number_format($product->CUSTOULTENT, 2, ',', '.') }}</span>
                            </div>
                            <div class="bg-blue-50 p-2 rounded border border-blue-100">
                                <span class="text-xs text-blue-500 block">Venda Atual</span>
                                <span class="text-lg font-bold text-blue-700">R$ {{ number_format($product->PVENDA, 2, ',', '.') }}</span>
                            </div>
                        </div>

                        {{-- Input Grande --}}
                        <div>
                            <label class="block text-center text-xs font-bold uppercase text-gray-500 mb-1">Novo Preço</label>
                            <input type="tel" x-ref="priceInput"
                                wire:model="novoPreco"
                                wire:keydown.enter="savePrice"
                                class="w-full text-center text-4xl font-bold border-2 border-primary-600 rounded-xl py-3 text-gray-900 focus:ring-4 focus:ring-primary-200 outline-none"
                                placeholder="0.00"
                            >
                        </div>

                        {{-- Botões --}}
                        <div class="grid grid-cols-2 gap-3 mt-2">
                            <button wire:click="resetCycle" class="py-3 bg-gray-200 font-bold rounded-lg text-gray-700">CANCELAR</button>
                            <button wire:click="savePrice" class="py-3 bg-primary-600 font-bold rounded-lg text-white shadow-lg">SALVAR</button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <script>
        function scannerLogic() {
            return {
                scanner: null,
                isScanning: false,
                isEditing: @json(!!$product),
                cameras: [],
                currentCameraId: localStorage.getItem('fil_scanner_cam_id'),

                initApp() {
                    // Escuta eventos do PHP
                    Livewire.on('product-found', () => {
                        this.playBeep();
                        this.isEditing = true;
                        this.stopScanner(); // Pausa a câmera enquanto edita
                        
                        // Foca no input
                        setTimeout(() => { if(this.$refs.priceInput) this.$refs.priceInput.focus(); }, 300);
                    });

                    Livewire.on('reset-scanner', () => {
                        this.isEditing = false;
                        this.startScanner(); // Volta a câmera automaticamente
                    });

                    Livewire.on('filial-selected', () => {
                        this.$nextTick(() => this.startScanner());
                    });

                    // Se já tiver filial, inicia
                    if (@json($filialId) && !this.isEditing) {
                        this.$nextTick(() => this.startScanner());
                    }
                },

                async startScanner() {
                    if (this.isScanning) return;
                    
                    try {
                        const devices = await Html5Qrcode.getCameras();
                        if (devices && devices.length) {
                            this.cameras = devices;
                            // Se não tiver câmera salva, pega a última (traseira geralmente)
                            if (!this.currentCameraId || !devices.find(c => c.id === this.currentCameraId)) {
                                this.currentCameraId = devices[devices.length - 1].id;
                            }
                        }

                        if(!document.getElementById('reader')) return;

                        this.scanner = new Html5Qrcode("reader");
                        await this.scanner.start(
                            this.currentCameraId, 
                            { fps: 10, qrbox: { width: 250, height: 250 } },
                            (decodedText) => {
                                console.log("Lido:", decodedText);
                                @this.handleBarcodeScan(decodedText);
                            },
                            () => {}
                        );
                        this.isScanning = true;
                    } catch (e) {
                        console.error("Erro câmera:", e);
                    }
                },

                async stopScanner() {
                    if (this.scanner && this.isScanning) {
                        await this.scanner.stop();
                        this.scanner.clear();
                        this.isScanning = false;
                    }
                },

                async switchCamera() {
                    if (this.cameras.length < 2) return;
                    await this.stopScanner();
                    
                    // Lógica circular
                    const idx = this.cameras.findIndex(c => c.id === this.currentCameraId);
                    const nextIdx = (idx + 1) % this.cameras.length;
                    this.currentCameraId = this.cameras[nextIdx].id;
                    
                    localStorage.setItem('fil_scanner_cam_id', this.currentCameraId);
                    await this.startScanner();
                },

                playBeep() {
                    const audio = document.getElementById('scan-sound');
                    if(audio) audio.play().catch(e => console.log(e));
                }
            }
        }
    </script>
</x-filament-panels::page>