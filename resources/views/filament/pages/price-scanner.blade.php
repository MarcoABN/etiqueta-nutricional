<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    {{-- Som de Beep --}}
    <audio id="scan-sound" src="{{ asset('sounds/beep.mp3') }}" preload="auto"></audio>

    <style>
        /* Remove interface do Filament */
        .fi-topbar,
        .fi-header,
        .fi-breadcrumbs,
        .fi-sidebar,
        .fi-footer {
            display: none !important;
        }

        .fi-main-ctn,
        .fi-page {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
        }

        /* Container principal fullscreen */
        .kiosk-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100dvh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Tela de seleção de filial */
        .filial-screen {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .filial-card {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .filial-card h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .filial-card p {
            text-align: center;
            color: #6b7280;
            margin-bottom: 2rem;
            font-size: 0.875rem;
        }

        .btn-start-scanner {
            width: 100%;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }

        .btn-start-scanner:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(139, 92, 246, 0.5);
        }

        .btn-start-scanner:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Área do Scanner */
        .scanner-screen {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #000;
        }

        /* Header do Scanner */
        .scanner-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 30;
            padding: 1rem;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.7) 0%, transparent 100%);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .filial-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1rem;
            border-radius: 12px;
            color: white;
        }

        .filial-badge-label {
            font-size: 0.625rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.7;
            display: block;
            margin-bottom: 0.25rem;
        }

        .filial-badge-value {
            font-size: 1.25rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        .exit-button {
            background: rgba(239, 68, 68, 0.9);
            backdrop-filter: blur(10px);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .exit-button:hover {
            background: rgba(239, 68, 68, 1);
            transform: translateY(-1px);
        }

        /* Viewport da Câmera */
        #scanner-viewport {
            flex: 1;
            position: relative;
            overflow: hidden;
            background: #000;
        }

        #reader {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #reader video {
            object-fit: cover;
            width: 100%;
            height: 100%;
        }

        /* Mira de Scan */
        .scan-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .scan-frame {
            width: 280px;
            height: 180px;
            border: 3px solid rgba(255, 255, 255, 0.6);
            border-radius: 16px;
            position: relative;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
        }

        .scan-frame::before,
        .scan-frame::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #ef4444, transparent);
        }

        .scan-frame::before {
            top: 50%;
            animation: scanLine 2s ease-in-out infinite;
        }

        @keyframes scanLine {

            0%,
            100% {
                top: 20%;
                opacity: 0;
            }

            50% {
                top: 80%;
                opacity: 1;
            }
        }

        .scan-corners {
            position: absolute;
            inset: -8px;
        }

        .scan-corner {
            position: absolute;
            width: 24px;
            height: 24px;
            border: 3px solid #3b82f6;
        }

        .scan-corner.top-left {
            top: 0;
            left: 0;
            border-right: 0;
            border-bottom: 0;
            border-radius: 16px 0 0 0;
        }

        .scan-corner.top-right {
            top: 0;
            right: 0;
            border-left: 0;
            border-bottom: 0;
            border-radius: 0 16px 0 0;
        }

        .scan-corner.bottom-left {
            bottom: 0;
            left: 0;
            border-right: 0;
            border-top: 0;
            border-radius: 0 0 0 16px;
        }

        .scan-corner.bottom-right {
            bottom: 0;
            right: 0;
            border-left: 0;
            border-top: 0;
            border-radius: 0 0 16px 0;
        }

        .scan-instruction {
            position: absolute;
            bottom: -60px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
        }

        /* Botão trocar câmera */
        .fab-camera {
            position: absolute;
            top: 5rem;
            right: 1rem;
            z-index: 40;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .fab-camera:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        /* Modal de Edição */
        #edit-modal {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top-left-radius: 28px;
            border-top-right-radius: 28px;
            padding: 1.5rem;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.3);
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 50;
            max-height: 85vh;
            overflow-y: auto;
        }

        #edit-modal.open {
            transform: translateY(0);
        }

        .modal-handle {
            width: 40px;
            height: 4px;
            background: #d1d5db;
            border-radius: 9999px;
            margin: 0 auto 1.5rem;
        }

        .product-header {
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .product-codes {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .product-code {
            font-size: 0.75rem;
            font-family: 'Courier New', monospace;
            color: #6b7280;
            background: #f3f4f6;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
        }

        .product-name {
            font-size: 1.125rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.4;
        }

        .price-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .price-box {
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
        }

        .price-box.cost {
            background: #f3f4f6;
            border: 2px solid #e5e7eb;
        }

        .price-box.current {
            background: #dbeafe;
            border: 2px solid #3b82f6;
        }

        .price-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .price-box.cost .price-label {
            color: #6b7280;
        }

        .price-box.current .price-label {
            color: #2563eb;
        }

        .price-value {
            font-size: 1.5rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        .price-box.cost .price-value {
            color: #374151;
        }

        .price-box.current .price-value {
            color: #1e40af;
        }

        .new-price-section {
            margin-bottom: 1.5rem;
        }

        .new-price-label {
            display: block;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-bottom: 0.75rem;
        }

        .new-price-input {
            width: 100%;
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            border: 3px solid #8b5cf6;
            border-radius: 16px;
            padding: 1rem;
            color: #111827;
            outline: none;
            transition: all 0.2s;
        }

        .new-price-input:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .btn {
            padding: 1rem;
            font-weight: 700;
            font-size: 0.875rem;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .btn-cancel {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-cancel:hover {
            background: #e5e7eb;
        }

        .btn-save {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(139, 92, 246, 0.5);
        }

        /* Animações */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .scanning-indicator {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>

    <div class="kiosk-container" x-data="scannerLogic()" x-init="initApp()">

        {{-- TELA 1: SELEÇÃO DE FILIAL --}}
        <template x-if="!hasFilial">
            <div class="filial-screen">
                <div class="filial-card">
                    <h2>🏪 Scanner de Preços</h2>
                    <p>Selecione a filial para iniciar</p>
                    {{ $this->form }}

                    <div style="margin-top: 1.5rem;">
                        <button wire:click="startScanner" class="btn-start-scanner" type="button">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                style="width: 20px; height: 20px; margin-right: 8px;" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Iniciar Scanner
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- TELA 2: SCANNER ATIVO --}}
        <template x-if="hasFilial">
            <div class="scanner-screen">
                {{-- Header --}}
                <div class="scanner-header">
                    <div class="filial-badge">
                        <span class="filial-badge-label">Filial</span>
                        <span class="filial-badge-value">{{ $filialId }}</span>
                    </div>
                    <button wire:click="changeFilial" class="exit-button">
                        SAIR
                    </button>
                </div>

                {{-- Botão Trocar Câmera --}}
                <button @click="switchCamera" class="fab-camera" x-show="cameras.length > 1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>

                {{-- Viewport da Câmera --}}
                <div id="scanner-viewport">
                    <div id="reader"></div>

                    {{-- Mira de Scan --}}
                    <div class="scan-overlay">
                        <div class="scan-frame">
                            <div class="scan-corners">
                                <div class="scan-corner top-left"></div>
                                <div class="scan-corner top-right"></div>
                                <div class="scan-corner bottom-left"></div>
                                <div class="scan-corner bottom-right"></div>
                            </div>
                            <div class="scan-instruction scanning-indicator">
                                📱 Posicione o código de barras
                            </div>
                        </div>
                    </div>
                </div>

                {{-- MODAL DE EDIÇÃO --}}
                <div id="edit-modal" :class="{ 'open': isEditing }">
                    @if($product)
                        <div class="modal-handle"></div>

                        {{-- Info do Produto --}}
                        <div class="product-header">
                            <div class="product-codes">
                                <span class="product-code">COD: {{ $product->CODPROD }}</span>
                                <span class="product-code">EAN: {{ $product->CODAUXILIAR }}</span>
                            </div>
                            <h3 class="product-name">{{ $product->DESCRICAO }}</h3>
                        </div>

                        {{-- Comparativo de Preços --}}
                        <div class="price-grid">
                            <div class="price-box cost">
                                <div class="price-label">💰 Custo</div>
                                <div class="price-value">R$ {{ number_format($product->CUSTOULTENT, 2, ',', '.') }}</div>
                            </div>
                            <div class="price-box current">
                                <div class="price-label">🏷️ Atual</div>
                                <div class="price-value">R$ {{ number_format($product->PVENDA, 2, ',', '.') }}</div>
                            </div>
                        </div>

                        {{-- Input Novo Preço --}}
                        <div class="new-price-section">
                            <label class="new-price-label">✨ Novo Preço</label>
                            <input type="tel" x-ref="priceInput" wire:model="novoPreco" wire:keydown.enter="savePrice"
                                class="new-price-input" placeholder="0,00" inputmode="decimal">
                        </div>

                        {{-- Botões --}}
                        <div class="action-buttons">
                            <button wire:click="cancelEdit" class="btn btn-cancel">
                                Cancelar
                            </button>
                            <button wire:click="savePrice" class="btn btn-save">
                                💾 Salvar
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </template>
    </div>

    <script>
        function scannerLogic() {
            return {
                scanner: null,
                isScanning: false,
                isProcessing: false, // <--- NOVO: Bloqueia leituras repetidas
                isEditing: @json(!!$product),
                hasFilial: @json(!!$filialId),
                cameras: [],
                currentCameraId: localStorage.getItem('fil_scanner_cam_id'),

                initApp() {
                    // SUCESSO: Produto encontrado
                    Livewire.on('product-found', () => {
                        this.playBeep();
                        this.isProcessing = false; // Libera o processamento
                        this.isEditing = true;
                        this.stopScanner();

                        setTimeout(() => {
                            if (this.$refs.priceInput) {
                                this.$refs.priceInput.focus();
                                this.$refs.priceInput.select();
                            }
                        }, 400);
                    });

                    // ERRO ou RESET: Produto não encontrado ou cancelado
                    Livewire.on('reset-scanner', () => {
                        this.isEditing = false;

                        // Mantém bloqueado por 2 segundos se for erro, para não bipar 
                        // o mesmo produto inválido instantaneamente de novo
                        setTimeout(() => {
                            this.isProcessing = false; // Libera para ler o próximo

                            // Se o scanner parou, reinicia
                            if (!this.isScanning) {
                                this.startScanner();
                            }
                        }, 2000);
                    });

                    Livewire.on('filial-selected', () => {
                        this.hasFilial = true;
                        this.$nextTick(() => {
                            setTimeout(() => this.startScanner(), 500);
                        });
                    });

                    if (this.hasFilial && !this.isEditing) {
                        this.$nextTick(() => {
                            setTimeout(() => this.startScanner(), 500);
                        });
                    }
                },

                async startScanner() {
                    if (this.isScanning) return;

                    try {
                        const devices = await Html5Qrcode.getCameras();
                        if (devices && devices.length) {
                            this.cameras = devices;
                            if (!this.currentCameraId || !devices.find(c => c.id === this.currentCameraId)) {
                                this.currentCameraId = devices[devices.length - 1].id;
                            }
                        }

                        const readerElement = document.getElementById('reader');
                        if (!readerElement) return;

                        this.scanner = new Html5Qrcode("reader");
                        await this.scanner.start(
                            this.currentCameraId,
                            {
                                fps: 10,
                                qrbox: { width: 280, height: 180 },
                                aspectRatio: 1.5
                            },
                            (decodedText) => {
                                // --- LÓGICA DE BLOQUEIO ---
                                if (this.isProcessing) return; // Ignora se já está processando um código

                                console.log("✅ Código lido e travado:", decodedText);
                                this.isProcessing = true; // Trava leituras imediatas
                                @this.handleBarcodeScan(decodedText);
                            },
                            () => { }
                        );
                        this.isScanning = true;
                    } catch (e) {
                        console.error("❌ Erro ao iniciar câmera:", e);
                    }
                },

                async stopScanner() {
                    if (this.scanner && this.isScanning) {
                        try {
                            await this.scanner.stop();
                            this.scanner.clear();
                            this.isScanning = false;
                        } catch (e) {
                            console.error("Erro ao parar scanner:", e);
                        }
                    }
                },

                async switchCamera() {
                    if (this.cameras.length < 2) return;
                    await this.stopScanner();
                    const idx = this.cameras.findIndex(c => c.id === this.currentCameraId);
                    const nextIdx = (idx + 1) % this.cameras.length;
                    this.currentCameraId = this.cameras[nextIdx].id;
                    localStorage.setItem('fil_scanner_cam_id', this.currentCameraId);
                    await this.startScanner();
                },

                playBeep() {
                    const audio = document.getElementById('scan-sound');
                    if (audio) audio.play().catch(e => console.log('🔇 Áudio bloqueado:', e));
                }
            }
        }
    </script>
</x-filament-panels::page>