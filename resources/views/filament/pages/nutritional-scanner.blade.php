<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>
        :root {
            --bg: #0f172a;
            --panel: #020617;
            --accent: #22c55e;
            --text: #e5e7eb;
            --muted: #94a3b8;
        }

        body {
            background: var(--bg);
        }

        .scanner-card {
            max-width: 480px;
            margin: 0 auto;
            background: var(--panel);
            border-radius: 14px;
            overflow: hidden;
        }

        .scanner-header {
            padding: 1rem;
            border-bottom: 1px solid #1e293b;
        }

        .scanner-header h1 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text);
        }

        .scanner-header span {
            font-size: 0.8rem;
            color: var(--muted);
        }

        .camera-area {
            padding: 0.75rem;
        }

        #reader {
            width: 100%;
            max-height: 55vh;
            aspect-ratio: 3 / 4;
            border-radius: 14px;
            overflow: hidden;
            background: #000;
        }

        .scanner-body {
            padding: 1rem;
            border-top: 1px solid #1e293b;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .produto {
            font-size: 0.9rem;
            color: var(--text);
        }

        .produto strong {
            display: block;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .action-btn {
            background: var(--accent);
            color: #052e16;
            font-weight: 600;
            padding: 0.7rem;
            border-radius: 10px;
            border: none;
        }

        .camera-select {
            display: none;
        }
    </style>

    <div class="scanner-card">
        <div class="scanner-header">
            <h1>Scanner de Produto</h1>
            <span>Leia o código de barras</span>
        </div>

        <div class="camera-area">
            <div id="reader"></div>
        </div>

        <div class="scanner-body">
            @if($produto)
                <div class="produto">
                    <strong>Produto identificado</strong>
                    {{ $produto['nome'] }} — {{ $produto['marca'] }}
                </div>

                <button class="action-btn">
                    Capturar etiqueta nutricional
                </button>
            @else
                <span style="color: var(--muted); font-size: 0.85rem;">
                    Aponte a câmera para o código de barras
                </span>
            @endif

            <div class="camera-select">
                <select id="cameraSelect"></select>
            </div>
        </div>
    </div>

    <script>
        const reader = new Html5Qrcode("reader");

        function startScanner(config) {
            reader.start(
                config,
                { fps: 10, qrbox: { width: 280, height: 160 } },
                (code) => {
                    reader.stop();
                    @this.call('barcodeDetected', code);
                }
            );
        }

        Html5Qrcode.getCameras().then(devices => {
            if (!devices.length) return;

            try {
                startScanner({ facingMode: { exact: "environment" } });
            } catch {
                const back = devices.find(d => /back|rear|environment/i.test(d.label));
                startScanner({ deviceId: { exact: (back ?? devices[0]).id } });
            }
        });
    </script>
</x-filament-panels::page>
