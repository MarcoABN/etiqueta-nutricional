<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Pallet {{ $pallet->pallet_number }}</title>
    <style>
        /* Tamanho físico do papel na impressora térmica */
        @page { 
            size: 100mm 80mm; 
            margin: 0; 
        }
        
        body { 
            margin: 0; 
            padding: 0; 
            background: white; 
        }
        
        /* Container delimitador da impressora (100x80 Horizontal) */
        .printer-container {
            width: 100mm;
            height: 80mm;
            position: relative;
            overflow: hidden;
        }

        /* Container virtual girado (80x100 Vertical) */
        .rotated-canvas {
            width: 80mm;
            height: 100mm;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-90deg);
            display: flex;
            flex-direction: column; 
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        /* CAIXAS RÍGIDAS DE 80x50mm PARA AS DUAS ETIQUETAS */
        .half-section {
            width: 80mm;
            height: 50mm;
            box-sizing: border-box;
            /* Margem ajustada: 4mm para garantir espaço lateral para o LLC não cortar */
            padding: 4mm 4mm; 
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;     
            text-align: center;      
            overflow: hidden;        
        }

        /* Força a primeira etiqueta a se afastar da borda inferior da impressora, mas sem espremer o texto */
        .section-importer {
            padding-top: 6mm; 
        }

        /* --- ESTILOS DO IMPORTADOR --- */
        .importer-text {
            width: 100%;
            text-transform: uppercase;
            margin: 0;
        }

        .line-big {
            font-size: 19pt; /* Reduzido de 20pt para garantir o encaixe horizontal perfeito */
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.5px;
            white-space: nowrap; 
        }

        .line-small {
            font-size: 12pt; /* Ajustado proporcionalmente */
            font-weight: bold;
            line-height: 1.2;
            margin-top: 3px; 
        }

        /* --- ESTILOS DOS DADOS DO PALLET --- */
        .data-text {
            font-size: 18pt; 
            font-weight: 900;
            line-height: 1.25;
            margin: 0;
            white-space: nowrap; 
            letter-spacing: -0.5px;
        }
    </style>
</head>
<body onload="window.print()">

    @php
        $rawImporter = strtoupper($pallet->importer_text ?? '');
        
        $rawImporter = str_replace(
            ['IMPORTED BY: GO MINAS DISTRIBUTION LLC', 'IMPORTED BY:  GO MINAS DISTRIBUTION LLC'],
            "IMPORTED BY:\nGO MINAS\nDISTRIBUTION LLC",
            $rawImporter
        );

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawImporter));
        $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));
    @endphp

    <div class="printer-container">
        <div class="rotated-canvas">
            
            <div class="half-section section-importer">
                <div class="importer-text">
                    @foreach($lines as $index => $line)
                        @if($index < 3)
                            <div class="line-big">{{ trim($line) }}</div>
                        @else
                            <div class="line-small">{{ trim($line) }}</div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="half-section">
                <div class="data-text">
                    PLT {{ $pallet->pallet_number }}/{{ $pallet->total_pallets }}<br>
                    G.W.: {{ number_format($pallet->gross_weight, 1, '.', '') }} KG<br>
                    Height: {{ number_format($pallet->height, 2, '.', '') }} m
                </div>
            </div>

        </div>
    </div>

</body>
</html>