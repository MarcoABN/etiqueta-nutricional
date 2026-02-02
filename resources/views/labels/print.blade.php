<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->product_name }}</title>
    <style>
        /* CONFIGURAÇÃO DA FOLHA FÍSICA */
        @page {
            size: 100mm 80mm;
            margin: 0; /* Remove margens do navegador para controle total */
        }
        
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
        }

        body {
            margin: 0;
            padding: 0;
            width: 100mm;
        }

        /* CONTAINER DA ETIQUETA */
        .label-instance {
            width: 100mm;
            height: 80mm;
            
            overflow: hidden; /* Corta qualquer excesso */
            position: relative;
            
            /* Força nova página após cada etiqueta */
            page-break-after: always;
            break-after: page;
        }

        /* Remove quebra da última página */
        .label-instance:last-child {
            page-break-after: auto;
            break-after: auto;
        }
    </style>
</head>
<body onload="setTimeout(() => window.print(), 300)">

    @for ($i = 0; $i < $qty; $i++)
        <div class="label-instance">
            @include('components.fda-label-template', [
                'product' => $product,
                'settings' => $settings
            ])
        </div>
    @endfor

</body>
</html>