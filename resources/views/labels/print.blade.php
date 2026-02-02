<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->product_name }}</title>
    <style>
        /* CONFIGURAÇÃO GLOBAL DA PÁGINA */
        @page {
            size: 100mm 80mm; /* Tamanho físico exato */
            margin: 0;        /* Zero margem para o navegador não deslocar nada */
        }

        html, body {
            margin: 0;
            padding: 0;
            background-color: white;
            /* Importante: permite que o corpo cresça para ter várias páginas */
            height: auto; 
            min-height: 100%;
        }

        /* O CONTAINER MÁGICO */
        .label-instance {
            /* 1. Tamanho Fixo */
            width: 100mm;
            height: 80mm;
            
            /* 2. Comportamento de Caixa */
            box-sizing: border-box; /* Garante que padding não aumente o tamanho total */
            overflow: hidden;       /* Corta qualquer excesso acidental */
            position: relative;
            display: block;         /* Garante comportamento de bloco */

            /* 3. A SOLUÇÃO DO PROBLEMA (QUEBRA DE PÁGINA) */
            /* Aplica a quebra DEPOIS de cada etiqueta */
            page-break-after: always; 
            break-after: page;
        }

        /* 4. EXCEÇÃO PARA A ÚLTIMA ETIQUETA */
        /* A última etiqueta NÃO deve gerar uma página em branco depois dela */
        .label-instance:last-child {
            page-break-after: auto;
            break-after: auto;
        }
    </style>
</head>
<body>

    {{-- Loop simples sem elementos estranhos no meio --}}
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
            // Delay leve para garantir que o CSS de quebra seja processado
            setTimeout(function() {
                window.print();
                
                // Opcional: Tenta fechar a janela após imprimir (ajuda no fluxo Kiosk)
                // window.close(); 
            }, 500);
        }
    </script>
</body>
</html>