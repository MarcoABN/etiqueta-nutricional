<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Solicitação #{{ $record->display_id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .info { margin-bottom: 20px; }
        
        .section-title { 
            background-color: #eee; 
            padding: 5px; 
            font-weight: bold; 
            margin-top: 20px; 
            border: 1px solid #ddd;
            border-bottom: none;
        }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f9f9f9; font-weight: bold; }
        
        /* Destaque visual para diferenciar */
        .manual-item { color: #555; font-style: italic; }

        @media print { 
            .no-print { display: none; } 
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print()">
    <button class="no-print" onclick="window.print()" style="padding: 10px; margin-bottom: 10px; cursor: pointer;">🖨️ Imprimir</button>

    <div class="header">
        <h1>SOLICITAÇÃO DE PEDIDO</h1>
        <h2>{{ $record->display_id }}</h2>
    </div>

    <div class="info">
        <strong>Data:</strong> {{ $record->created_at->format('d/m/Y H:i') }} <br>
        <strong>Status:</strong> {{ ucfirst($record->status) }} <br>
    </div>

    @php
        // Separa os itens em duas coleções
        $registeredItems = $record->items->filter(fn($item) => !empty($item->product_id));
        $manualItems = $record->items->filter(fn($item) => empty($item->product_id));
    @endphp

    {{-- 1. TABELA DE PRODUTOS CADASTRADOS --}}
    @if($registeredItems->isNotEmpty())
        <div class="section-title">📦 Produtos Cadastrados (WinThor)</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%">Cód.</th>
                    <th style="width: 45%">Produto</th>
                    <th style="width: 10%">Qtd</th>
                    <th style="width: 10%">Emb.</th>
                    <th style="width: 10%">Envio</th>
                    <th style="width: 15%">Obs</th>
                </tr>
            </thead>
            <tbody>
                @foreach($registeredItems as $item)
                <tr>
                    <td>{{ $item->winthor_code }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ number_format($item->quantity, 2, ',', '.') }}</td>
                    <td>{{ $item->packaging }}</td>
                    <td>{{ $item->shipping_type }}</td>
                    <td>{{ $item->observation }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- 2. TABELA DE ITENS MANUAIS --}}
    @if($manualItems->isNotEmpty())
        <div class="section-title">📝 Itens Adicionais / Manuais (Sem Cadastro)</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 55%">Descrição do Item</th>
                    <th style="width: 10%">Qtd</th>
                    <th style="width: 10%">Emb.</th>
                    <th style="width: 10%">Envio</th>
                    <th style="width: 15%">Obs</th>
                </tr>
            </thead>
            <tbody>
                @foreach($manualItems as $item)
                <tr class="manual-item">
                    <td>{{ $item->product_name }}</td>
                    <td>{{ number_format($item->quantity, 2, ',', '.') }}</td>
                    <td>{{ $item->packaging }}</td>
                    <td>{{ $item->shipping_type }}</td>
                    <td>{{ $item->observation }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($registeredItems->isEmpty() && $manualItems->isEmpty())
        <p style="text-align: center; color: #999; margin-top: 50px;">Nenhum item inserido nesta solicitação.</p>
    @endif

</body>
</html>