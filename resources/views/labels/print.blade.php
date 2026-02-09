<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->product_name }}</title>
    <style>
        @page { size: 100mm 80mm; margin: 0; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        body { margin: 0; padding: 0; width: 100mm; height: 80mm; }

        .sheet-instance {
            width: 100mm; 
            height: 80mm;
            position: relative;
            overflow: hidden;
            page-break-after: always; 
            break-after: page;
        }
        .sheet-instance:last-child { page-break-after: auto; }
    </style>
</head>
<body onload="setTimeout(() => window.print(), 300)">

    @php 
        $layout = request('layout', 'standard'); 
    @endphp

    @if($layout === 'standard')
        @for ($i = 0; $i < $qty; $i++)
            <div class="sheet-instance">
                @include('components.fda-label-template', ['product' => $product, 'settings' => $settings])
            </div>
        @endfor
    @else
        {{-- MODO TWIN (TABULAR) --}}
        @php $sheetsNeeded = ceil($qty / 2); @endphp
        @for ($s = 0; $s < $sheetsNeeded; $s++)
            <div class="sheet-instance">
                @include('components.fda-label-tabular', ['product' => $product, 'settings' => $settings])
            </div>
        @endfor
    @endif

</body>
</html>