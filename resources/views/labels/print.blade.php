<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->product_name }}</title>
    <style>
        /* 1. Reset Total */
        @page {
            size: 100mm 80mm;
            margin: 0; 
        }
        
        body {
            margin: 0;
            padding: 0;
        }

        /* 2. Container da Etiqueta */
        .label-instance {
            width: 100mm;
            /* TRUQUE: Usamos 79mm ou 79.5mm para garantir que cabe na folha sem estourar */
            /* A impressora vai avançar até o Gap (picote) físico de qualquer forma */
            height: 79.5mm; 
            
            overflow: hidden;
            position: relative;
            box-sizing: border-box;
            
            /* Força a quebra DEPOIS deste elemento */
            page-break-after: always;
            break-after: page;
        }

        /* 3. Remove a quebra da última para não soltar papel em branco no final */
        .label-instance:last-child {
            page-break-after: auto;
            break-after: auto;
        }
    </style>
</head>
<body>

    @for ($i = 0; $i < $qty; $i++)
        <div class="label-instance">
            @include('components.fda-label-template', [
                'product' => $product,
                'settings' => $settings,
                'overrideWidth' => '100mm'
            ])
        </div>
    @endfor

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 300);
        }
    </script>
</body>
</html>