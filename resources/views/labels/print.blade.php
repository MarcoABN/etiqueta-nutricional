<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->product_name }}</title>
    <style>
        /* Configuração Física da Página no Navegador */
        @page {
            size: 100mm 80mm; /* Tamanho exato */
            margin: 0;        /* Sem margens no navegador, controlamos no CSS */
        }

        body {
            margin: 0;
            padding: 0;
            background-color: white;
            font-family: Helvetica, Arial, sans-serif;
        }

        /* Container da Etiqueta */
        .label-instance {
            width: 100mm;
            height: 80mm;
            overflow: hidden; /* Garante que não "vaze" para a pág seguinte sem querer */
            position: relative;
            box-sizing: border-box;
        }

        /* Elemento Forçador de Quebra de Página */
        .page-break {
            page-break-after: always; /* Padrão antigo */
            break-after: page;        /* Padrão novo */
            height: 0;
            display: block;
            visibility: hidden;
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

        @if ($i < $qty - 1)
            <div class="page-break"></div>
        @endif
    @endfor

    <script>
        // Dispara impressão automaticamente ao carregar
        window.onload = function() {
            // Pequeno delay para garantir renderização das fontes
            setTimeout(function() {
                window.print();
            }, 300);
        }
    </script>
</body>
</html>