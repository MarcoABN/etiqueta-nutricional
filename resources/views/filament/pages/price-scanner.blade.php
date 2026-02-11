<x-filament-panels::page>
    {{-- Importando a mesma lib que você usava no NutritionalScanner --}}
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    {{-- Som de Beep (Replicado do seu arquivo original) --}}
    <audio id="scan-sound" src="{{ asset('sounds/beep.mp3') }}" preload="auto"></audio>

    <div x-data="priceScannerHandler()" x-init="init()" class="flex flex-col gap-4">

        {{-- PASSO 1: SELEÇÃO DE FILIAL --}}
        @if(!$showScanner)
            <div class="max-w-md mx-auto w-full bg-white p-6 rounded-xl shadow-lg dark:bg-gray-800">
                <h2 class="text-xl font-bold mb-4 text-center">Selecionar Filial</h2>
                {{ $this->form }}
            </div>
        @else
            {{-- PASSO 2: ÁREA DO SCANNER --}}
            <div class="flex justify-between items-center bg-white p-3 rounded-lg shadow dark:bg-gray-800">
                <div>
                    <span class="text-xs text-gray-500 uppercase font-bold">Filial Ativa</span>
                    <div class="font-mono text-2xl font-bold text-primary-600">{{ $filialId }}</div>
                </div>
                <button wire:click="changeFilial" class="text-sm font-bold text-red-500 border border-red-200 px-4 py-2 rounded hover:bg-red-50 transition">
                    TROCAR
                </button>
            </div>

            {{-- Container da Câmera --}}
            <div class="relative bg-black rounded-xl overflow-hidden shadow-lg" style="min-height: 350px;">
                
                {{-- O elemento ID reader é onde a lib desenha a câmera --}}
                <div id="reader" class="w-full h-full" wire:ignore></div>

                {{-- Overlay de Carregamento --}}
                <div x-show="!scannerReady && !hasProduct" class="absolute inset-0 flex items-center justify-center text-white bg-black/80 z-10">
                    <div class="text-center">
                        <svg class="animate-spin h-10 w-10 text-white mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p>Iniciando Câmera...</p>
                    </div>
                </div>
            </div>

            {{-- PASSO 3: FORMULÁRIO DE PREÇO (Overlay) --}}
            @if($product)
                <div class="fixed inset-0 z-50 bg-gray-900/95 flex items-center justify-center p-4 overflow-y-auto"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    
                    <div class="bg-white dark:bg-gray-800 w-full max-w-lg rounded-xl shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-700">
                        
                        {{-- Cabeçalho do Produto --}}
                        <div class="bg-primary-600 p-5 text-white">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-mono opacity-80 block">COD: {{ $product->CODPROD }}</span>
                                    <span class="text-xs font-mono opacity-80 block">EAN: {{ $product->CODAUXILIAR }}</span>
                                </div>
                                <span class="bg-white/20 text-xs px-2 py-1 rounded">Em Estoque: {{ number_format($product->QTESTOQUE, 0) }}</span>
                            </div>
                            <h2 class="text-xl font-bold leading-tight mt-2">{{ $product->DESCRICAO }}</h2>
                        </div>

                        <div class="p-6 space-y-6">
                            
                            {{-- Grid de Comparação --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg border border-gray-200 dark:border-gray-600">
                                    <span class="block text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Custo Atual</span>
                                    <span class="text-xl font-bold text-gray-700 dark:text-gray-200">
                                        R$ {{ number_format($product->CUSTOULTENT, 2, ',', '.') }}
                                    </span>
                                </div>
                                <div class="bg-blue-50 dark:bg-blue-900/30 p-3 rounded-lg border border-blue-100 dark:border-blue-800">
                                    <span class="block text-xs text-blue-600 dark:text-blue-400 uppercase font-bold">Venda Atual</span>
                                    <span class="text-xl font-bold text-blue-700 dark:text-blue-300">
                                        R$ {{ number_format($product->PVENDA, 2, ',', '.') }}
                                    </span>
                                </div>
                            </div>

                            {{-- Input Gigante --}}
                            <div>
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase text-center">
                                    Novo Preço de Venda
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xl font-bold">R$</span>
                                    <input type="number" step="0.01" inputmode="decimal"
                                        x-ref="priceInput"
                                        wire:model="novoPreco"
                                        wire:keydown.enter="savePrice"
                                        class="w-full text-center text-4xl font-bold text-gray-900 border-2 border-primary-500 rounded-xl py-4 pl-8 focus:ring-4 focus:ring-primary-200 focus:border-primary-600 shadow-inner"
                                        placeholder="0.00"
                                    >
                                </div>
                            </div>

                            {{-- Botões de Ação --}}
                            <div class="grid grid-cols-2 gap-4 pt-2">
                                <button wire:click="resetCycle" class="py-4 bg-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-300 transition text-lg">
                                    CANCELAR
                                </button>
                                <button wire:click="savePrice" class="py-4 bg-primary-600 text-white font-bold rounded-xl shadow-lg hover:bg-primary-700 transition transform active:scale-95 text-lg">
                                    SALVAR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>

    <script>
        function priceScannerHandler() {
            return {
                scanner: null,
                scannerReady: false,
                hasProduct: @json(!!$product),

                init() {
                    // Espera o DOM carregar completamente
                    this.$nextTick(() => {
                        this.startScanner();
                    });

                    // Eventos do Livewire para controlar o ciclo
                    Livewire.on('play-beep', () => {
                        const audio = document.getElementById('scan-sound');
                        if (audio) audio.play().catch(e => console.log('Erro audio:', e));
                    });

                    Livewire.on('reset-scanner', () => {
                        this.hasProduct = false;
                        this.startScanner(); // Reinicia scanner
                    });

                    Livewire.on('focus-price', () => {
                        this.hasProduct = true;
                        this.stopScanner(); // Para scanner para economizar bateria/processamento
                        setTimeout(() => {
                            if (this.$refs.priceInput) this.$refs.priceInput.focus();
                        }, 300);
                    });
                },

                startScanner() {
                    // Se já estiver rodando ou não tiver a div reader, sai
                    if (this.scanner || !document.getElementById('reader')) return;

                    // Config igual ao seu arquivo original
                    this.scanner = new Html5QrcodeScanner(
                        "reader", 
                        { 
                            fps: 10, 
                            qrbox: 250,
                            aspectRatio: 1.0 
                        },
                        /* verbose= */ false
                    );

                    this.scanner.render((decodedText) => {
                        // Sucesso
                        console.log("Lido: " + decodedText);
                        @this.handleBarcodeScan(decodedText);
                    }, (errorMessage) => {
                        // Erro de leitura (comum, ignora)
                    });

                    this.scannerReady = true;
                },

                stopScanner() {
                    if (this.scanner) {
                        this.scanner.clear().then(() => {
                            this.scanner = null;
                            this.scannerReady = false;
                        }).catch((error) => {
                            console.error("Falha ao limpar scanner", error);
                        });
                    }
                }
            }
        }
    </script>

    <style>
        /* Ajustes visuais para a lib html5-qrcode ficar bonita */
        #reader { border: none !important; }
        #reader__scan_region { background: white; }
        #reader__dashboard_section_csr button { 
            background-color: #e5e7eb; 
            padding: 5px 10px; 
            border-radius: 5px; 
            margin-top: 10px;
            font-weight: bold;
        }
    </style>
</x-filament-panels::page>