<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->product_name }} - Print</title>
    <style>
        /* Reseta margens do navegador para controle total via CSS */
        @page {
            size: 114mm 80mm;
            margin: 0; 
        }
        body {
            margin: 0;
            padding: 0;
            background-color: white;
        }
        
        /* Quebra de página para múltiplas etiquetas */
        .page-break {
            page-break-after: always;
        }

        /* Oculta elementos de debug na impressão se houver */
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    @for ($i = 0; $i < $qty; $i++)
        @include('components.fda-label-template', [
            'product' => $product,
            'settings' => $settings 
        ])

        @if ($i < $qty - 1)
            <div class="page-break"></div>
        @endif
    @endfor

    <script>
        // Dispara a impressão assim que carregar
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>