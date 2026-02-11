<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    {{-- Som de Beep --}}
    <audio id="scan-sound" src="{{ asset('sounds/beep.mp3') }}" preload="auto"></audio>

    {{-- CSS "Quiosque" para esconder UI do Filament --}}
    <style>
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; color: white; position: relative; }
        
        /* Containers */
        #app-container { position: absolute; inset: 0; display: flex; flex-direction: column; }
        #scanner-view { flex: 1; position: relative; background: black; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        #scanner-video { width: 100%; height: 100%; object-fit: cover; }
        
        /* Overlay de Edição */
        #edit-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.95); z-index: 50; display: flex; flex-direction: column; padding: 20px; overflow-y: auto; }
        
        /* Botão Switch Câmera */
        .camera-switch { position: absolute; bottom: 30px; right: 30px; z-index: 10; background: rgba(255,255,255,0.2); border-radius: 50%; p-3; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.3); }
    </style>

    <div id="app-container" x-data="scannerApp()" x-init="initApp()">

        {{-- TELA 1: SELEÇÃO DE FILIAL --}}
        @if(!$filialId)
            <div class="flex-1 flex items-center justify-center bg-gray-900 p-6">
                <div class="w-full max-w-md bg-white text-gray-900 rounded-xl shadow-2xl p-8">
                    <h2 class="text-2xl font-bold mb-6 text-center">Configurar Scanner</h2>
                    {{ $this->form }}
                </div>
            </div>
        @else
            
            {{-- TELA 2: SCANNER (Sempre renderizado, escondido se editando) --}}
            <div id="scanner-view" x-show="!isEditing">
                {{-- Elemento onde o HTML5-QRCode injeta o vídeo --}}
                <div id="reader" style="width: 100%; height: 100%;"></div>

                {{-- Overlay informativo --}}
                <div class="absolute top-0 left-0 right-0 p-4 flex justify-between items-start bg-gradient-to-b from-black/80 to-transparent z-10">
                    <div>
                        <div class="text-xs text-gray-400 uppercase font-bold">FILIAL</div>
                        <div class="text-2xl font-bold text-white">{{ $filialId }}</div>
                    </div>
                    <button wire:click="changeFilial" class="text-xs bg-red-600/80 text-white px-3 py-1 rounded hover:bg-red-600">
                        SAIR
                    </button>
                </div>

                {{-- Botão Trocar Câmera --}}
                <button x-show="cameras.length > 1" @click="switchCamera()" class="absolute bottom-8 right-8 z-20 bg-white/20 p-4 rounded-full backdrop-blur-md border border-white/30 text-white shadow-lg active:scale-95 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>

                {{-- Mira Central --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                    <div class="w-64 h-64 border-2 border-white/50 rounded-lg relative">
                        <div class="absolute top-0 left-0 w-4 h-4 border-t-4 border-l-4 border-green-500 -mt-1 -ml-1"></div>
                        <div class="absolute top-0 right-0 w-4 h-4 border-t-4 border-r-4 border-green-500 -mt-1 -mr-1"></div>
                        <div class="absolute bottom-0 left-0 w-4 h-4 border-b-4 border-l-4 border-green-500 -mb-1 -ml-1"></div>
                        <div class="absolute bottom-0 right-0 w-4 h-4 border-b-4 border-r-4 border-green-500 -mb-1 -mr-1"></div>
                    </div>
                </div>
            </div>

            {{-- TELA 3: EDITAR PRODUTO (Overlay) --}}
            @if($product)
                <div id="edit-overlay" x-data x-trap="true">
                    <div class="w-full max-w-lg mx-auto bg-white rounded-xl shadow-2xl overflow-hidden flex flex-col h-auto max-h-full">
                        {{-- Header Produto --}}
                        <div class="bg-primary-600 p-5 text-white shrink-0">
                            <div class="flex justify-between items-start opacity-90 text-sm font-mono mb-1">
                                <span>COD: {{ $product->CODPROD }}</span>
                                <span>EAN: {{ $product->CODAUXILIAR }}</span>
                            </div>
                            <h2 class="text-xl font-bold leading-tight">{{ $product->DESCRICAO }}</h2>
                        </div>

                        {{-- Corpo Formulário --}}
                        <div class="p-6 space-y-6 overflow-y-auto">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-100 p-3 rounded border border-gray-200">
                                    <span class="block text-xs text-gray-500 uppercase font-bold">Custo</span>
                                    <span class="text-xl font-bold text-gray-800">R$ {{ number_format($product->CUSTOULTENT, 2, ',', '.') }}</span>
                                </div>
                                <div class="bg-blue-50 p-3 rounded border border-blue-100">
                                    <span class="block text-xs text-blue-500 uppercase font-bold">Venda Atual</span>
                                    <span class="text-xl font-bold text-blue-700">R$ {{ number_format($product->PVENDA, 2, ',', '.') }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-center text-sm font-bold text-gray-700 uppercase mb-2">Novo Preço</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xl font-bold">R$</span>
                                    <input type="number" step="0.01" inputmode="decimal"
                                        x-ref="priceInput"
                                        wire:model="novoPreco"
                                        wire:keydown.enter="savePrice"
                                        class="w-full text-center text-5xl font-bold text-gray-900 border-2 border-primary-500 rounded-xl py-4 pl-8 focus:ring-4 focus:ring-primary-200 outline-none"
                                        placeholder="0.00"
                                    >
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 pt-4">
                                <button wire:click="resetCycle" class="py-4 bg-gray-200 text-gray-700 font-bold rounded-xl text-lg hover:bg-gray-300">
                                    CANCELAR
                                </button>
                                <button wire:click="savePrice" class="py-4 bg-primary-600 text-white font-bold rounded-xl text-lg shadow-lg hover:bg-primary-700 active:scale-95 transition">
                                    SALVAR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        @endif
    </div>

    {{-- LÓGICA JAVASCRIPT ROBUSTA --}}
    <script>
        function scannerApp() {
            return {
                scanner: null,
                isScanning: false,
                isEditing: @json(!!$product),
                cameras: [],
                currentCameraId: localStorage.getItem('price_scanner_camera_id'),

                initApp() {
                    // Listener global para sons e resets
                    Livewire.on('product-found', () => {
                        this.playBeep();
                        this.isEditing = true;
                        this.stopScanner(); // Pausa câmera para economizar
                        
                        // Foca no input após renderizar
                        setTimeout(() => {
                            if(this.$refs.priceInput) this.$refs.priceInput.focus();
                        }, 300);
                    });

                    Livewire.on('reset-scanner', () => {
                        this.isEditing = false;
                        this.startScanner(); // Reinicia câmera automaticamente
                    });

                    Livewire.on('filial-selected', () => {
                        this.startScanner();
                    });

                    // Se já tiver filial e não estiver editando, inicia
                    if(@json($filialId) && !this.isEditing) {
                        this.startScanner();
                    }
                },

                async startScanner() {
                    if (this.isScanning) return;

                    // Aguarda renderização do DIV reader
                    await this.$nextTick();
                    const readerEl = document.getElementById('reader');
                    if (!readerEl) return;

                    try {
                        // Lista Câmeras
                        const devices = await Html5Qrcode.getCameras();
                        if (devices && devices.length) {
                            this.cameras = devices;
                            
                            // Se a câmera salva não existir mais, pega a última (geralmente traseira)
                            if (!this.currentCameraId || !devices.find(c => c.id === this.currentCameraId)) {
                                this.currentCameraId = devices[devices.length - 1].id;
                            }
                        }

                        this.scanner = new Html5Qrcode("reader");
                        
                        await this.scanner.start(
                            this.currentCameraId, 
                            { fps: 10, qrbox: { width: 250, height: 250 } },
                            (decodedText) => {
                                console.log("Lido:", decodedText);
                                @this.handleBarcodeScan(decodedText);
                            },
                            () => {} // Ignora erros de frame vazio
                        );
                        
                        this.isScanning = true;

                    } catch (err) {
                        console.error("Erro Câmera:", err);
                        alert("Erro ao acessar câmera. Verifique permissões HTTPS.");
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
                    
                    // Lógica de Ciclo
                    const currentIndex = this.cameras.findIndex(c => c.id === this.currentCameraId);
                    const nextIndex = (currentIndex + 1) % this.cameras.length;
                    this.currentCameraId = this.cameras[nextIndex].id;
                    
                    // Salva preferência
                    localStorage.setItem('price_scanner_camera_id', this.currentCameraId);
                    
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