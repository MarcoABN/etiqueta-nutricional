@props(['product', 'settings'])

@php
    // Configurações de Fallback
    $settings = $settings ?? new \App\Models\LabelSetting([
        'padding_top' => 2,
        'padding_left' => 2,
        'padding_right' => 2,
        'padding_bottom' => 2,
        'gap_width' => 6,
        'font_scale' => 100
    ]);

    $scale = $settings->font_scale / 100;

    // Lista de Micronutrientes
    $micronutrients = collect([
        ['Vitamin D', $product->vitamin_d],
        ['Calcium', $product->calcium],
        ['Iron', $product->iron],
        ['Potassium', $product->potassium],
        ['Vitamin A', $product->vitamin_a],
        ['Vitamin C', $product->vitamin_c],
        ['Vitamin E', $product->vitamin_e],
        ['Thiamin', $product->thiamin],
        ['Riboflavin', $product->riboflavin],
        ['Niacin', $product->niacin],
        ['Vitamin B6', $product->vitamin_b6],
        ['Folate', $product->folate],
        ['Vitamin B12', $product->vitamin_b12],
        ['Biotin', $product->biotin],
        ['Pantothenic Acid', $product->pantothenic_acid],
        ['Phosphorus', $product->phosphorus],
        ['Iodine', $product->iodine],
        ['Magnesium', $product->magnesium],
        ['Zinc', $product->zinc],
        ['Selenium', $product->selenium],
        ['Copper', $product->copper],
        ['Manganese', $product->manganese],
        ['Chromium', $product->chromium],
        ['Molybdenum', $product->molybdenum],
        ['Chloride', $product->chloride],
    ])
    ->filter(function($item) {
        $val = $item[1];
        if (blank($val)) return false;
        $numeric = (float) preg_replace('/[^0-9.]/', '', $val);
        return $numeric > 0;
    }) 
    ->map(fn($item) => "{$item[0]} {$item[1]}")
    ->implode(', ');

    $hasMicros = !empty($micronutrients);
@endphp

<div class="fda-label-container bg-white text-black overflow-hidden relative"
     style="
        width: 100%; 
        height: 100%; 
        font-family: Helvetica, Arial, sans-serif; 
        box-sizing: border-box; 
        padding-top: {{ $settings->padding_top }}mm;
        padding-bottom: {{ $settings->padding_bottom }}mm;
        padding-left: {{ $settings->padding_left }}mm;
        padding-right: {{ $settings->padding_right }}mm;
     ">
    
    <style>
        .fda-label-container { border: 1px dashed #e5e7eb; }
        @media print { .fda-label-container { border: none !important; } }
    </style>

    <div style="
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: {{ $settings->gap_width }}mm; 
        height: 100%;
    ">
        <div class="nutrition-facts" style="font-size: 7.5pt; line-height: 1.1; display: flex; flex-direction: column;">
            
            <div>
                <div style="font-weight: 900; font-size: 16pt; margin: 0; line-height: 1;">Nutrition Facts</div>
                <div style="display: flex; justify-content: space-between; margin-top: 1px;">
                    <span>{{ $product->servings_per_container }} servings per container</span>
                </div>
                <div style="border-bottom: 6px solid black; padding-bottom: 1px; margin-bottom: 1px; font-weight: 800; display: flex; justify-content: space-between;">
                    <span>Serving size</span>
                    <span style="font-size: 7pt;">{{ $product->serving_size_quantity }} {{ $product->serving_size_unit }} ({{ $product->serving_weight }}g)</span>
                </div>
                <div style="border-bottom: 4px solid black; padding-bottom: 1px; display: flex; justify-content: space-between; align-items: flex-end;">
                    <div style="line-height: 1.2;">
                        <span style="font-weight: normal; font-size: 6pt; display: block;">Amount per serving</span>
                        <span style="font-weight: 900; font-size: 14pt;">Calories</span>
                    </div>
                    <span style="font-weight: 900; font-size: 22pt; line-height: 0.8;">{{ $product->calories }}</span>
                </div>
                <div style="text-align: right; border-bottom: 1px solid black; font-size: 6pt; font-weight: bold;">% Daily Value*</div>
            </div>

            @foreach([
                ['Total Fat', $product->total_fat.'g', $product->total_fat_dv ?? '0', true],
                ['Saturated Fat', $product->sat_fat.'g', $product->sat_fat_dv ?? '0', false, true],
                ['Trans Fat', $product->trans_fat.'g', $product->trans_fat_dv ?? '0', false, true],
                ['Cholesterol', $product->cholesterol.'mg', $product->cholesterol_dv ?? '0', true],
                ['Sodium', $product->sodium.'mg', $product->sodium_dv ?? '0', true],
                ['Total Carb.', $product->total_carb.'g', $product->total_carb_dv ?? '0', true],
                ['Dietary Fiber', $product->fiber.'g', $product->fiber_dv ?? '0', false, true],
                ['Total Sugars', $product->total_sugars.'g', $product->total_sugars_dv ?? '', false, true], 
                ['Incl. Added Sugars', $product->added_sugars.'g', $product->added_sugars_dv ?? '0', false, true],
                ['Protein', $product->protein.'g', $product->protein_dv, true],
            ] as $nutri)
                <div style="border-top: 1px solid #000; display: flex; justify-content: space-between; {{ isset($nutri[4]) ? 'padding-left: 8px;' : '' }}">
                    <span>
                        @if($nutri[3]) <strong>{{ $nutri[0] }}</strong> @else {{ $nutri[0] }} @endif 
                        {{ $nutri[1] }}
                    </span>
                    @if($nutri[2] !== '' && $nutri[2] !== null) 
                        <strong>{{ $nutri[2] }}%</strong> 
                    @endif
                </div>
            @endforeach
            
            <div style="border-top: 4px solid black; font-size: 5pt; line-height: 1.1; margin-top: 2px; margin-bottom: 2px;">
                * The % Daily Value (DV) tells you how much a nutrient in a serving of food contributes to a daily diet. 2,000 calories a day is used for general nutrition advice.
            </div>

            <div style="margin-top: auto; padding-top: 2px; border-top: 1px solid black; font-size: 5pt; line-height: 1.0; font-weight: bold;">
                @if(filled($product->imported_by))
                    {!! nl2br(e($product->imported_by)) !!}
                @else
                    IMPORTED BY:<br>
                    GO MINAS DISTRIBUTION LLC<br>
                    2042 NW 55TH AVE, MARGATE, FL33063
                @endif
            </div>
        </div>

        <div style="font-size: 7pt; display: flex; flex-direction: column; overflow: hidden; padding-left: 2px;">
            
            <div class="product-title-fit" style="
                font-weight: bold; 
                font-size: 9pt; 
                margin-bottom: 4px; 
                text-transform: uppercase; 
                line-height: 1.1; 
                max-height: 2.25em; 
                display: block; 
                overflow: hidden;
            ">
                {{ $product->product_name_en ?? $product->product_name }}
            </div>

            @if($hasMicros)
                <div style="margin-bottom: 4px; font-size: 6.5pt; line-height: 1.1;">
                    <strong>VITAMINS AND MINERALS:</strong> {{ $micronutrients }}.
                </div>
            @endif

            <div class="auto-fit-ingredients" style="margin-bottom: 4px; flex-grow: 1; overflow: hidden;">
                <strong>INGREDIENTS:</strong> {{ $product->ingredients }}
            </div>

            @if($product->allergens_contains)
                <div style="margin-bottom: 2px; flex-shrink: 0;"><strong>CONTAINS:</strong> {{ $product->allergens_contains }}</div>
            @endif
            
            @if($product->allergens_may_contain)
                <div style="margin-bottom: 4px; flex-shrink: 0;"><strong>MAY CONTAIN:</strong> {{ $product->allergens_may_contain }}</div>
            @endif

            <div style="margin-top: auto; line-height: 1.2; flex-shrink: 0; text-align: right; padding-bottom: 1px;">
                <strong>Product of {{ $product->origin_country ?? 'Brazil' }}</strong>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const scaleFactor = {{ $scale }};

        // Função para Ingredientes (Preenche o espaço restante)
        function fitTextGeneric(selector, minSizePt) {
            const elements = document.querySelectorAll(selector);
            const minPx = minSizePt * 1.33 * scaleFactor;

            elements.forEach(el => {
                let size = parseFloat(window.getComputedStyle(el).fontSize);
                // Enquanto o conteúdo for maior que a caixa, diminui
                while (el.offsetHeight > 0 && (el.scrollHeight > el.clientHeight) && size > minPx) {
                    size -= 0.2;
                    el.style.fontSize = size + 'px';
                }
            });
        }

        // Função Específica para Título (2 Linhas Rígidas)
        function fitTwoLines(selector, minSizePt) {
            const elements = document.querySelectorAll(selector);
            const minPx = minSizePt * 1.33 * scaleFactor;

            elements.forEach(el => {
                let currentFontSize = parseFloat(window.getComputedStyle(el).fontSize);
                
                // A lógica agora é simples: como definimos max-height: 2.25em no CSS,
                // se o scrollHeight for maior que o offsetHeight, significa que
                // o texto está tentando ocupar mais que o espaço permitido (3+ linhas).
                
                while (el.scrollHeight > el.offsetHeight && currentFontSize > minPx) {
                    currentFontSize -= 0.1; // Redução fina
                    el.style.fontSize = currentFontSize + 'px';
                }
            });
        }
        
        setTimeout(() => {
            // Título: Minimo 3.5pt para garantir que caiba em 2 linhas
            fitTwoLines('.product-title-fit', 3.5); 
            
            // Ingredientes: Minimo 4.5pt
            fitTextGeneric('.auto-fit-ingredients', 4.5);
        }, 100);
    })();
</script>