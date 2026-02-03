<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>
        body {
            margin: 0;
            background: #000;
        }

        .scanner-wrapper {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100%;
            justify-content: center;
            align-items: center;
            gap: 1rem;
        }

        #reader {
            width: 100%;
            max-width: 420px;
            aspect-ratio: 3 / 4;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
        }

        .scanner-info {
            color: #fff;
            font-size: 0.9rem;
            opacity: 0.85;
        }

        .camera-select {
            display: none;
            width: 100%;
            max-width: 420px;
        }

        select {
            width: 100%;
            padding: 0.6rem;
            border-radius: 8px;
            border: none;
        }
    </style>

    <div class="scanner-wrapper">
        <div id="reader"></div>

        <div class="scanner-info">
            Aponte a câmera para o código de barras
        </div>

        <div class="camera-select">
            <select id="cameraSelect"></select>
        </div>
    </div>

    <script>
        const reader = new Html5Qrcode("reader");
        const cameraSelect = document.getElementById('cameraSelect');
        const cameraWrapper = document.querySelector('.camera-select');

        function startScanner(cameraConfig) {
            reader.start(
                cameraConfig,
                { fps: 10, qrbox: { width: 250, height: 150 } },
                (decodedText) => {
                    reader.stop();
                    @this.call('barcodeDetected', decodedText);
                }
            );
        }

        Html5Qrcode.getCameras().then(devices => {
            if (!devices.length) return;

            // 1️⃣ tenta câmera traseira pelo facingMode
            startScanner({ facingMode: { exact: "environment" } });

        }).catch(() => {
            // 2️⃣ fallback: escolher câmera traseira pelo label
            Html5Qrcode.getCameras().then(devices => {
                let preferred = devices.find(d =>
                    /back|rear|environment/i.test(d.label)
                );

                if (preferred) {
                    startScanner({ deviceId: { exact: preferred.id } });
                } else {
                    // 3️⃣ último caso: exibir seletor
                    cameraWrapper.style.display = 'block';

                    devices.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.id;
                        option.text = device.label || 'Câmera';
                        cameraSelect.appendChild(option);
                    });

                    cameraSelect.onchange = () => {
                        reader.stop().then(() => {
                            startScanner({ deviceId: { exact: cameraSelect.value } });
                        });
                    };

                    startScanner({ deviceId: { exact: devices[0].id } });
                }
            });
        });
    </script>
</x-filament-panels::page>
