@props(['product', 'settings', 'unit' => null])

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

    // LÓGICA DE CONVERSÃO (Onça vs Grama)
    $requestUnit = $unit ?? request('unit', 'oz'); 

    $convert = function($value, $fromUnit) use ($requestUnit) {
        $val = floatval($value);
        if ($val <= 0) return 0;
        if ($requestUnit === 'g') return $val; 

        // Converte para Onça (oz)
        if ($fromUnit === 'g') return round($val * 0.035274, 2);
        if ($fromUnit === 'mg') return round($val * 0.000035274, 4);
        if ($fromUnit === 'mcg') return round($val * 0.000000035274, 6);
        
        return $val;
    };

    $getUnit = function($fromUnit) use ($requestUnit) {
        return $requestUnit === 'g' ? $fromUnit : 'oz';
    };

    // Função para calcular %VD com regras de arredondamento oficiais da FDA
    $calculateDV = function($amount, $dv) {
        $val = floatval($amount);
        if ($val <= 0 || $dv <= 0) return 0;
        
        $percent = ($val / $dv) * 100;
        
        if ($percent < 1) return 0;
        if ($percent <= 10) return round($percent / 2) * 2;
        if ($percent <= 50) return round($percent / 5) * 5;
        return round($percent / 10) * 10;
    };

    // Conversão do Serving Weight
    $rawServingWeight = preg_replace('/[^0-9.]/', '', $product->serving_weight ?? '0');
    $displayServingWeight = $convert($rawServingWeight, 'g') . $getUnit('g');

    // --- 1. LÓGICA DE MICRONUTRIENTES ---
    // Extraindo os 4 obrigatórios da FDA com cálculo automático de %VD
    // Base Diária FDA: Vit D (20mcg), Calcium (1300mg), Iron (18mg), Potassium (4700mg)
    $mandatoryMicrosData = [
        ['Vitamin D', $product->vitamin_d ?? 0, 'mcg', $calculateDV($product->vitamin_d ?? 0, 20)],
        ['Calcium', $product->calcium ?? 0, 'mg', $calculateDV($product->calcium ?? 0, 1300)],
        ['Iron', $product->iron ?? 0, 'mg', $calculateDV($product->iron ?? 0, 18)],
        ['Potassium', $product->potassium ?? 0, 'mg', $calculateDV($product->potassium ?? 0, 4700)],
    ];

    $mandatoryMicrosList = [];
    foreach ($mandatoryMicrosData as $micro) {
        $mandatoryMicrosList[] = [
            $micro[0], 
            $convert($micro[1], $micro[2]), 
            $getUnit($micro[2]), 
            $micro[3]
        ];
    }

    // Demais micronutrientes para a coluna da direita
    $allMicronutrients = [
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
        $originalVal = $micro[1];
        $originalUnit = $micro[2];

        if (floatval($originalVal) > 0) {
            $convertedVal = $convert($originalVal, $originalUnit);
            $finalUnit = $getUnit($originalUnit);
            $finalMicros->push("{$label} {$convertedVal}{$finalUnit}");
        }
    }

    $micronutrientsString = $finalMicros->implode(', ');
    $hasMicros = $finalMicros->isNotEmpty();

    // --- 2. LÓGICA DE MACRONUTRIENTES ---
    $nutrientsList = [
        ['Total Fat', $convert($product->total_fat, 'g').$getUnit('g'), ($product->total_fat_dv ?? '0'), true],
        ['Saturated Fat', $convert($product->sat_fat, 'g').$getUnit('g'), ($product->sat_fat_dv ?? '0'), false, true],
        ['Trans Fat', $convert($product->trans_fat, 'g').$getUnit('g'), '', false, true], 
        ['Cholesterol', $convert($product->cholesterol, 'mg').$getUnit('mg'), ($product->cholesterol_dv ?? '0'), true],
        ['Sodium', $convert($product->sodium, 'mg').$getUnit('mg'), ($product->sodium_dv ?? '0'), true],
        ['Total Carb.', $convert($product->total_carb, 'g').$getUnit('g'), ($product->total_carb_dv ?? '0'), true],
        ['Dietary Fiber', $convert($product->fiber, 'g').$getUnit('g'), ($product->fiber_dv ?? '0'), false, true],
        ['Total Sugars', $convert($product->total_sugars, 'g').$getUnit('g'), '', false, true], 
        ['Incl. Added Sugars', $convert($product->added_sugars, 'g').$getUnit('g'), ($product->added_sugars_dv ?? '0'), false, true],
        ['Protein', $convert($product->protein, 'g').$getUnit('g'), ($product->protein_dv ?? '0'), true],
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
                <div style="font-weight: 900; font-size: 13.5pt; margin: 0; line-height: 1; white-space: nowrap; letter-spacing: -0.2px;">Nutrition Facts</div>
                
                <div style="border-bottom: 1px solid black; margin-top: 2px; margin-bottom: 2px;"></div>

                <div style="display: flex; justify-content: space-between; margin-top: 1px;">
                    <span>{{ $product->servings_per_container ?? 'Varied' }} servings per container</span>
                </div>
                <div style="border-bottom: 4.5px solid black; padding-bottom: 1px; margin-bottom: 1px; font-weight: 800; display: flex; justify-content: space-between;">
                    <span>Serving size</span>
                    <span style="font-size: 7pt;">
                        {{ $product->serving_size_quantity ?? '0' }} {{ $product->serving_size_unit }} 
                        ({{ $displayServingWeight }})
                    </span>
                </div>
                <div style="border-bottom: 3px solid black; padding-bottom: 1px; display: flex; justify-content: space-between; align-items: flex-end;">
                    <div style="line-height: 1.2;">
                        <span style="font-weight: normal; font-size: 6pt; display: block;">Amount per serving</span>
                        <span style="font-weight: 900; font-size: 11pt;">Calories</span>
                    </div>
                    <span style="font-weight: 900; font-size: 18pt; line-height: 0.8;">{{ $product->calories ?? '0' }}</span>
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
            
            <div style="border-top: 3px solid black; margin-top: 1px; margin-bottom: 0px;"></div>

            {{-- 4 MICRONUTRIENTES OBRIGATÓRIOS (Vit D, Calcium, Iron, Potassium) --}}
            @foreach($mandatoryMicrosList as $micro)
                <div style="{{ $loop->first ? '' : 'border-top: 1px solid #000;' }} display: flex; justify-content: space-between;">
                    <span>{{ $micro[0] }} {{ $micro[1] }}{{ $micro[2] }}</span>
                    <span>{{ $micro[3] }}%</span>
                </div>
            @endforeach

            <div style="margin-top: auto; border-top: 3px solid black; font-size: 5pt; line-height: 1.1; padding-top: 2px; margin-bottom: 2px;">
                * The % Daily Value (DV) tells you how much a nutrient in a serving of food contributes to a daily diet. 2,000 calories a day is used for general nutrition advice.
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

            <div style="margin-top: auto; flex-shrink: 0; padding-bottom: 1px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; line-height: 1.1;">
                    <div style="font-size: 5pt; font-weight: bold; text-align: left;">
                        @if(filled($product->imported_by))
                            {!! nl2br(e($product->imported_by)) !!}
                        @else
                            IMPORTED BY:<br>
                            GO MINAS DISTRIBUTION LLC<br>
                            2042 NW 55TH AVE, MARGATE, FL33063
                        @endif
                    </div>
                    
                    <div style="font-size: 7pt; font-weight: bold; text-align: right; margin-left: 5px;">
                        Product of {{ $product->origin_country ?? 'Brazil' }}
                    </div>
                </div>
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