<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->product_name }}</title>
    <style>
        /* 1. Definição da Folha Física */
        @page {
            /* Tamanho exato solicitado */
            size: 100mm 80mm;
            /* Margem Zero é crucial para não deslocar */
            margin: 0mm; 
        }
        
        /* 2. Reset Global */
        * {
            box-sizing: border-box; /* Garante que padding não aumente largura */
            -webkit-print-color-adjust: exact;
        }

        body {
            margin: 0;
            padding: 0;
            width: 100mm; /* Trava a largura do corpo */
        }

        /* 3. Container da Etiqueta Individual */
        .label-instance {
            width: 100mm;   /* Largura exata da folha */
            height: 80mm;   /* Altura exata da folha */
            
            overflow: hidden; /* Corta qualquer pixel que estoure */
            position: relative;
            
            /* Força a quebra de página DEPOIS de cada etiqueta */
            page-break-after: always;
            break-after: page;
        }

        /* 4. Remove a quebra da última para não cuspir papel em branco no final */
        .label-instance:last-child {
            page-break-after: auto;
            break-after: auto;
        }
    </style>
</head>
<body onload="window.print()">

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