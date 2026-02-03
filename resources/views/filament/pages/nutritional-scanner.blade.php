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

        .scanner-layout {
            display: grid;
            grid-template-rows: auto 1fr auto;
            height: 100vh;
            max-width: 480px;
            margin: 0 auto;
            background: var(--panel);
        }

        .scanner-header {
            padding: 1rem;
            border-bottom: 1px solid #020617;
        }

        .scanner-header h1 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
        }

        .scanner-header span {
            font-size: 0.8rem;
            color: var(--muted);
        }

        .camera-container {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
        }

        #reader {
            width: 100%;
            aspect-ratio: 3 / 4;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid #020617;
        }

        .scanner-footer {
            padding: 1rem;
            border-top: 1px solid #020617;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .status {
            font-size: 0.85rem;
            color: var(--muted);
        }

        .status.active {
            color: var(--accent);
            font-weight: 500;
        }

        .action-btn {
            display: none;
            background: var(--accent);
            color: #052e16;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 10px;
            border: none;
            cursor: pointer;
        }

        .camera-select {
            display: none;
        }

        select {
            width: 100%;
            padding: 0.5rem;
            border-radius: 8px;
            background: #020617;
            color: var(--text);
            border: 1px solid #1e293b;
        }
    </style>

    <div class="scanner-layout">
        <div class="scanner-header">
            <h1>Scanner de Produto</h1>
            <span>Leitura de código de barras</span>
        </div>

        <div class="camera-container">
            <div id="reader"></div>
        </div>

        <div class="scanner-footer">
            <div class="status active" id="status">
                Aguardando leitura do código...
            </div>

            <div class="camera-select">
                <select id="cameraSelect"></select>
            </div>

            <button class="action-btn" id="captureBtn">
                Capturar foto do código
            </button>
        </div>
    </div>

    <script>
        const reader = new Html5Qrcode("reader");
        const status = document.getElementById('status');
        const captureBtn = document.getElementById('captureBtn');
        const cameraSelect = document.getElementById('cameraSelect');
        const cameraWrapper = document.querySelector('.camera-select');

        function updateStatus(text, active = false) {
            status.textContent = text;
            status.classList.toggle('active', active);
        }

        function startScanner(config) {
            reader.start(
                config,
                {
                    fps: 10,
                    qrbox: { width: 280, height: 160 }
                },
                (code) => {
                    updateStatus('Código identificado ✔', true);
                    reader.stop();
                    captureBtn.style.display = 'block';
                    @this.call('barcodeDetected', code);
                }
            );
        }

        Html5Qrcode.getCameras().then(devices => {
            if (!devices.length) return;

            // tentativa 1: traseira
            try {
                startScanner({ facingMode: { exact: "environment" } });
            } catch {
                // tentativa 2: label
                const backCam = devices.find(d => /back|rear|environment/i.test(d.label));
                if (backCam) {
                    startScanner({ deviceId: { exact: backCam.id } });
                } else {
                    // fallback controlado
                    cameraWrapper.style.display = 'block';

                    devices.forEach(d => {
                        const o = document.createElement('option');
                        o.value = d.id;
                        o.text = d.label || 'Câmera';
                        cameraSelect.appendChild(o);
                    });

                    cameraSelect.onchange = () => {
                        reader.stop().then(() =>
                            startScanner({ deviceId: { exact: cameraSelect.value } })
                        );
                    };

                    startScanner({ deviceId: { exact: devices[0].id } });
                }
            }
        });
    </script>
</x-filament-panels::page>
