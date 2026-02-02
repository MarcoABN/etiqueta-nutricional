<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Etiqueta</title>
    <style>
        /* Configuração exata para Zebra ZT231 (114mm x 80mm) */
        @media print {
            @page {
                size: 114mm 80mm;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
            }
        }
        body {
            font-family: Arial, sans-serif;
            width: 114mm;
            height: 80mm;
            /* Estilize sua etiqueta aqui */
            padding: 5mm;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div style="border: 2px solid black; padding: 10px; height: 100%;">
        <h1>{{ $produto->nome ?? 'Produto Teste' }}</h1>
        <p>Código: {{ $produto->codigo ?? '123456' }}</p>
        <div style="text-align: center; margin-top: 20px;">
            [CÓDIGO DE BARRAS AQUI]
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
            // Opcional: fechar a janela após imprimir (funciona bem no modo Kiosk)
            setTimeout(function(){ window.close(); }, 500);
        }
    </script>
</body>
</html>