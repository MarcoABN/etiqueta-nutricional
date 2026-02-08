@props(['product', 'settings'])

@php
    $settings = $settings ?? new \App\Models\LabelSetting([
        'padding_top' => 2,
        'padding_left' => 2,
        'padding_right' => 2,
        'padding_bottom' => 2,
        'gap_width' => 6,
        'font_scale' => 100
    ]);

    $scale = $settings->font_scale / 100;

    // --- 1. LÓGICA DE MICRONUTRIENTES ---
    // LISTA ÚNICA: A regra agora é: SÓ MOSTRA SE O VALOR FOR MAIOR QUE ZERO.
    
    $allMicronutrients = [
        // Prioritários (Antigos Obrigatórios)
        ['Vitamin D', $product->vitamin_d, 'mcg'],
        ['Calcium', $product->calcium, 'mg'],
        ['Iron', $product->iron, 'mg'],
        ['Potassium', $product->potassium, 'mg'],
        
        // Demais (Voluntários)
        ['Vitamin A', $product->vitamin_a, 'mcg'],
        ['Vitamin C', $product->vitamin_c, 'mg'],
        ['Vitamin E', $product->vitamin_e, 'mg'],
        ['Thiamin', $product->thiamin, 'mg'],
        ['Riboflavin', $product->riboflavin, 'mg'],
        ['Niacin', $product->niacin, 'mg'],
        ['Vitamin B6', $product->vitamin_b6, 'mg'],
        ['Folate', $product->folate, 'mcg'],
        ['Vitamin B12', $product->vitamin_b12, 'mcg'],
        ['Biotin', $product->biotin, 'mcg'],
        ['Pantothenic Acid', $product->pantothenic_acid, 'mg'],
        ['Phosphorus', $product->phosphorus, 'mg'],
        ['Iodine', $product->iodine, 'mcg'],
        ['Magnesium', $product->magnesium, 'mg'],
        ['Zinc', $product->zinc, 'mg'],
        ['Selenium', $product->selenium, 'mcg'],
        ['Copper', $product->copper, 'mcg'],
        ['Manganese', $product->manganese, 'mg'],
        ['Chromium', $product->chromium, 'mcg'],
        ['Molybdenum', $product->molybdenum, 'mcg'],
        ['Chloride', $product->chloride, 'mg'],
    ];

    $finalMicros = collect();

    foreach ($allMicronutrients as $micro) {
        $label = $micro[0];
        $val   = $micro[1];
        $unit  = $micro[2];

        // AQUI ESTÁ A CORREÇÃO:
        // Usa floatval para garantir que null, "", "0", "0.00" sejam tratados como zero.
        // Só adiciona na lista se for estritamente maior que 0.
        if (floatval($val) > 0) {
            $finalMicros->push("{$label} {$val}{$unit}");
        }
    }

    $micronutrientsString = $finalMicros->implode(', ');
    $hasMicros = $finalMicros->isNotEmpty();


    // --- 2. LÓGICA DE MACRONUTRIENTES ---
    
    $nutrientsList = [
        // Label, Valor, %VD, Negrito?, Indentado?
        ['Total Fat', ($product->total_fat ?? '0').'g', ($product->total_fat_dv ?? '0'), true],
        ['Saturated Fat', ($product->sat_fat ?? '0').'g', ($product->sat_fat_dv ?? '0'), false, true],
        ['Trans Fat', ($product->trans_fat ?? '0').'g', '', false, true], 
        ['Cholesterol', ($product->cholesterol ?? '0').'mg', ($product->cholesterol_dv ?? '0'), true],
        ['Sodium', ($product->sodium ?? '0').'mg', ($product->sodium_dv ?? '0'), true],
        ['Total Carb.', ($product->total_carb ?? '0').'g', ($product->total_carb_dv ?? '0'), true],
        ['Dietary Fiber', ($product->fiber ?? '0').'g', ($product->fiber_dv ?? '0'), false, true],
        ['Total Sugars', ($product->total_sugars ?? '0').'g', '', false, true], 
        ['Incl. Added Sugars', ($product->added_sugars ?? '0').'g', ($product->added_sugars_dv ?? '0'), false, true],
        ['Protein', ($product->protein ?? '0').'g', ($product->protein_dv ?? '0'), true],
    ];
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
        {{-- COLUNA DA ESQUERDA: TABELA NUTRICIONAL --}}
        <div class="nutrition-facts" style="font-size: 7.5pt; line-height: 1.1; display: flex; flex-direction: column;">
            
            <div>
                <div style="font-weight: 900; font-size: 16pt; margin: 0; line-height: 1;">Nutrition Facts</div>
                <div style="display: flex; justify-content: space-between; margin-top: 1px;">
                    <span>{{ $product->servings_per_container ?? 'Varied' }} servings per container</span>
                </div>
                <div style="border-bottom: 6px solid black; padding-bottom: 1px; margin-bottom: 1px; font-weight: 800; display: flex; justify-content: space-between;">
                    <span>Serving size</span>
                    <span style="font-size: 7pt;">
                        {{ $product->serving_size_quantity ?? '0' }} {{ $product->serving_size_unit }} 
                        ({{ $product->serving_weight ?? '0g' }})
                    </span>
                </div>
                <div style="border-bottom: 4px solid black; padding-bottom: 1px; display: flex; justify-content: space-between; align-items: flex-end;">
                    <div style="line-height: 1.2;">
                        <span style="font-weight: normal; font-size: 6pt; display: block;">Amount per serving</span>
                        <span style="font-weight: 900; font-size: 14pt;">Calories</span>
                    </div>
                    <span style="font-weight: 900; font-size: 22pt; line-height: 0.8;">{{ $product->calories ?? '0' }}</span>
                </div>
                <div style="text-align: right; border-bottom: 1px solid black; font-size: 6pt; font-weight: bold;">% Daily Value*</div>
            </div>

            @foreach($nutrientsList as $nutri)
                <div style="border-top: 1px solid #000; display: flex; justify-content: space-between; {{ isset($nutri[4]) ? 'padding-left: 8px;' : '' }}">
                    <span>
                        @if($nutri[3]) <strong>{{ $nutri[0] }}</strong> @else {{ $nutri[0] }} @endif 
                        {{ $nutri[1] }}
                    </span>
                    @if($nutri[2] !== '') <strong>{{ $nutri[2] }}%</strong> @endif
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

        {{-- COLUNA DA DIREITA: INGREDIENTES E EXTRAS --}}
        <div style="font-size: 7pt; display: flex; flex-direction: column; overflow: hidden; padding-left: 2px;">
            
            <div class="product-title-fit" style="
                font-weight: bold; 
                font-size: 9pt; 
                margin-bottom: 4px; 
                text-transform: uppercase; 
                line-height: 1.1; 
                display: block;
                word-wrap: break-word;
            ">
                {{ $product->product_name_en ?? $product->product_name }}
            </div>

            {{-- SEÇÃO DE VITAMINAS CORRIGIDA: Só exibe se $hasMicros for true --}}
            @if($hasMicros)
                <div style="margin-bottom: 4px; font-size: 6.5pt; line-height: 1.1;">
                    <strong>VITAMINS AND MINERALS:</strong> {{ $micronutrientsString }}.
                </div>
            @endif

            <div class="auto-fit-ingredients" style="margin-bottom: 4px; flex-grow: 1; overflow: hidden;">
                <strong>INGREDIENTS:</strong> {{ $product->ingredients ?? 'Ingredients not available.' }}
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

        function fitTextGeneric(selector, minSizePt) {
            const elements = document.querySelectorAll(selector);
            const minPx = minSizePt * 1.33 * scaleFactor;
            elements.forEach(el => {
                let size = parseFloat(window.getComputedStyle(el).fontSize);
                while (el.offsetHeight > 0 && (el.scrollHeight > el.clientHeight) && size > minPx) {
                    size -= 0.2;
                    el.style.fontSize = size + 'px';
                }
            });
        }

        function fitTwoLines(selector, minSizePt) {
            const elements = document.querySelectorAll(selector);
            const minPx = minSizePt * 1.33 * scaleFactor;
            elements.forEach(el => {
                el.style.lineHeight = '1.1';
                el.style.maxHeight = 'none';
                let currentSizePx = parseFloat(window.getComputedStyle(el).fontSize);
                const checkFits = (size) => {
                    const maxAllowedHeight = size * 1.1 * 2.1;
                    return el.scrollHeight <= maxAllowedHeight;
                };
                while (!checkFits(currentSizePx) && currentSizePx > minPx) {
                    currentSizePx -= 0.5; 
                    el.style.fontSize = currentSizePx + 'px';
                }
            });
        }
        
        setTimeout(() => {
            fitTwoLines('.product-title-fit', 3.0); 
            fitTextGeneric('.auto-fit-ingredients', 4.5);
        }, 200); 
    })();
</script>