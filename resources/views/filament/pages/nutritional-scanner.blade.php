<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* --- MODO IMERSIVO (APP-LIKE) --- */
        .fi-topbar, .fi-header, .fi-breadcrumbs { display: none !important; }
        .fi-main-ctn { 
            padding: 0 !important; 
            max-width: 100% !important; 
            margin: 0 !important; 
        }
        .fi-page { padding: 1rem; }
        .filepond--panel-root {
            background-color: #f0fdf4;
            border: 2px dashed #22c55e;
        }
        .filepond--drop-label {
            color: #15803d;
            font-weight: bold;
            font-size: 1.1rem;
        }
    </style>

    {{-- BARRA DE TÍTULO PERSONALIZADA --}}
    <div class="fixed top-0 left-0 right-0 z-50 bg-white dark:bg-gray-900 border-b shadow-sm px-4 py-3 flex justify-between items-center h-14">
        <span class="font-bold text-lg text-gray-800 dark:text-white">Coletor</span>
        <span class="text-xs font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
            {{ $foundProduct ? 'PASSO 2/2' : 'PASSO 1/2' }}
        </span>
    </div>

    {{-- Espaçador --}}
    <div class="h-14"></div>

    {{-- CONTEÚDO --}}
    <div class="max-w-md mx-auto h-[calc(100vh-4rem)] flex flex-col">

        {{-- LÓGICA PRINCIPAL: SCANNER OU UPLOAD --}}
        @if(!$foundProduct)
            
            {{-- PASSO 1: SCANNER --}}
            <div class="flex-1 flex flex-col justify-center items-center space-y-6">
                <div class="w-full bg-black rounded-xl overflow-hidden shadow-2xl relative aspect-square">
                    <div id="reader" class="w-full h-full object-cover"></div>
                    <div class="absolute inset-0 pointer-events-none border-2 border-red-500/60 m-12 rounded-lg"></div>
                    <div class="absolute bottom-4 w-full text-center text-white/80 text-xs font-bold uppercase tracking-widest">
                        Aponte para o EAN
                    </div>
                </div>
                <p class="text-gray-500 text-sm px-8 text-center">
                    Posicione o código de barras dentro da área demarcada.
                </p>
            </div>

        @else

            {{-- PASSO 2: PRODUTO & FOTO --}}
            <div class="flex-1 flex flex-col justify-between pb-4">
                <div class="space-y-4">
                    {{-- Card do Produto --}}
                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-600 p-4 rounded shadow-sm">
                        <div class="text-xs text-blue-600 dark:text-blue-400 font-bold uppercase tracking-wide mb-1">
                            Produto Identificado
                        </div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                            {{ $foundProduct->product_name }}
                        </h2>
                        <p class="text-sm text-gray-500 font-mono mt-1">EAN: {{ $scannedCode }}</p>
                    </div>

                    {{-- Botão/Área de Foto --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 pl-1">
                            Tabela Nutricional
                        </label>
                        {{ $this->form }}
                    </div>
                </div>

                {{-- Botões Fixos na Base --}}
                <div class="mt-auto pt-4 flex gap-3">
                    <button wire:click="resetScanner" type="button" class="w-[30%] h-14 bg-gray-200 active:bg-gray-300 text-gray-700 font-bold rounded-xl flex items-center justify-center transition-colors">
                        VOLTAR
                    </button>
                    <button wire:click="save" type="button" class="w-[70%] h-14 bg-green-600 active:bg-green-700 text-white font-bold rounded-xl flex items-center justify-center shadow-lg transition-transform active:scale-95 gap-2">
                        <x-heroicon-m-camera class="w-6 h-6" />
                        SALVAR FOTO
                    </button>
                </div>
            </div>

        @endif {{-- FECHAMENTO DO BLOCO IF/ELSE --}}

    </div>

    {{-- Scripts --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            let scanner = null;

            function initScanner() {
                // Se já achou produto (Passo 2), não liga o scanner
                if (@json($foundProduct)) return;
                
                if (scanner) { try { scanner.clear(); } catch(e) {} }

                scanner = new Html5QrcodeScanner("reader", { 
                    fps: 10, 
                    qrbox: {width: 250, height: 150},
                    aspectRatio: 1.0,
                    showTorchButtonIfSupported: true,
                    rememberLastUsedCamera: true
                }, false);

                scanner.render(onScanSuccess, onScanFailure);
            }

            function onScanSuccess(decodedText) {
                if (scanner) scanner.clear();
                @this.handleBarcodeScan(decodedText);
            }

            function onScanFailure(error) {
                if (error?.includes("permission")) alert("Erro de Permissão/HTTPS!");
            }

            initScanner();
            Livewire.on('reset-scanner', () => setTimeout(initScanner, 300));
        });
    </script>
</x-filament-panels::page>