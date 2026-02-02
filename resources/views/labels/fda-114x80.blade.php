<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->product_name }} - Print</title>
    <style>
        /* 1. Define o tamanho exato da página física no navegador */
        @page {
            size: 100mm 80mm; /* CORRIGIDO PARA 100mm */
            margin: 0;        /* O navegador não deve por margem, o componente cuida disso */
        }

        body {
            margin: 0;
            padding: 0;
            background-color: white;
            font-family: Helvetica, Arial, sans-serif;
        }

        /* 2. O Wrapper que garante a quebra de página */
        .label-page {
            width: 100mm;     /* Largura fixa */
            height: 80mm;     /* Altura fixa */
            overflow: hidden; /* Impede que conteúdo extra crie uma página branca em branco */
            position: relative;
            
            /* COMANDOS DE QUEBRA DE PÁGINA */
            page-break-after: always; /* Para navegadores antigos */
            break-after: page;        /* Para Chrome/Edge modernos */
        }

        /* A última etiqueta não precisa forçar uma quebra de página depois dela */
        .label-page:last-child {
            page-break-after: auto;
            break-after: auto;
        }
    </style>
</head>
<body>

    @for ($i = 0; $i < $qty; $i++)
        <div class="label-page">
            @include('components.fda-label-template', [
                'product' => $product,
                'settings' => $settings,
                'overrideWidth' => '100mm' // Passamos uma flag nova
            ])
        </div>
    @endfor

    <script>
        // Pequeno delay para garantir que o CSS carregou antes de imprimir
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>