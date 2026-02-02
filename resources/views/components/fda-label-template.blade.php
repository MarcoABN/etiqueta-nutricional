@props(['product', 'settings', 'overrideWidth' => '100mm'])

@php
    // 1. Fallback para configurações (caso não venha do controller)
    // Valores padrão seguros para começar
    $settings = $settings ?? new \App\Models\LabelSetting([
        'padding_top' => 2,
        'padding_left' => 2,
        'padding_right' => 2,
        'padding_bottom' => 2,
        'gap_width' => 6, // Espaço do corte no meio
        'font_scale' => 100
    ]);

    // Fator de escala da fonte
    $scale = $settings->font_scale / 100;

    // 2. Preparar lista de Micronutrientes (Linear Display)
    // Formato: "Calcium 270mg (27%)"
    // Filtra apenas os que têm valor preenchido
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
    ])->filter(fn($item) => filled($item[1])) 
    ->map(function($item) {
        return "{$item[0]} {$item[1]}";
    })->implode(', ');

    $hasMicros = !empty($micronutrients);
@endphp

<div class="fda-label-container bg-white text-black overflow-hidden relative box-border"
     style="
        width: {{ $overrideWidth }}; 
        height: 80mm; 
        font-family: Helvetica, Arial, sans-serif; 
        border: 1px dashed #ccc; /* Borda visual para debug (não sai na térmica se margem for 0) */
        
        /* Margens Dinâmicas vindas do Banco */
        padding-top: {{ $settings->padding_top }}mm;
        padding-bottom: {{ $settings->padding_bottom }}mm;
        padding-left: {{ $settings->padding_left }}mm;
        padding-right: {{ $settings->padding_right }}mm;
     ">
    
    <div style="
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: {{ $settings->gap_width }}mm; 
        height: 100%;
    ">
        
        <div class="nutrition-facts" style="font-size: 7.5pt; line-height: 1.1; display: flex; flex-direction: column;">
            
            <div>
                <div style="font-weight: 900; font-size: 16pt; margin: 0; line-height: 1;">Nutrition Facts</div>
                
                <div style="display: flex; justify-content: space-between; margin-top: 2px;">
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
                ['Total Fat', $product->total_fat.'g', $product->total_fat_dv, true],
                ['Saturated Fat', $product->sat_fat.'g', $product->sat_fat_dv, false, true],
                ['Trans Fat', $product->trans_fat.'g', '', false, true],
                ['Cholesterol', $product->cholesterol.'mg', $product->cholesterol_dv, true],
                ['Sodium', $product->sodium.'mg', $product->sodium_dv, true],
                ['Total Carb.', $product->total_carb.'g', $product->total_carb_dv, true],
                ['Dietary Fiber', $product->fiber.'g', $product->fiber_dv, false, true],
                ['Total Sugars', $product->total_sugars.'g', '', false, true],
                ['Incl. Added Sugars', $product->added_sugars.'g', $product->added_sugars_dv, false, true],
                ['Protein', $product->protein.'g', $product->protein_dv, true],
            ] as $nutri)
                <div style="border-top: 1px solid #000; display: flex; justify-content: space-between; {{ isset($nutri[4]) ? 'padding-left: 8px;' : '' }}">
                    <span>
                        @if($nutri[3]) <strong>{{ $nutri[0] }}</strong> @else {{ $nutri[0] }} @endif 
                        {{ $nutri[1] }}
                    </span>
                    @if($nutri[2]) <strong>{{ $nutri[2] }}%</strong> @endif
                </div>
            @endforeach
            
            <div style="
                border-top: 4px solid black; 
                font-size: 5pt; 
                line-height: 1.25; 
                margin-top: 4px; 
                margin-bottom: 6px; 
                padding-top: 2px;
                color: #000;
            ">
                * The % Daily Value (DV) tells you how much a nutrient in a serving of food contributes to a daily diet. 2,000 calories a day is used for general nutrition advice.
            </div>

            <div style="margin-top: auto; padding-top: 2px; border-top: 1px solid black; font-size: 5pt; line-height: 1.1; font-weight: bold;">
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
            
            <div class="auto-fit" style="font-weight: bold; font-size: 9pt; margin-bottom: 4px; text-transform: uppercase; max-height: 32px; overflow: hidden; line-height: 1.1;">
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
                <div style="margin-bottom: 6px; flex-shrink: 0;"><strong>MAY CONTAIN:</strong> {{ $product->allergens_may_contain }}</div>
            @endif

            <div style="margin-top: auto; line-height: 1.2; flex-shrink: 0; text-align: right;">
                <strong>Product of {{ $product->origin_country ?? 'Brazil' }}</strong>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        // Função para ajustar texto ao container
        function fitText(selector, minSize = 5) {
            const elements = document.querySelectorAll(selector);
            
            // Fator de escala vindo do Banco de Dados
            const scaleFactor = {{ $scale }};
            
            elements.forEach(el => {
                let size = parseFloat(window.getComputedStyle(el).fontSize);
                
                // Limite mínimo ajustado pela escala global
                const localMin = minSize * scaleFactor;

                // Enquanto o conteúdo estourar a altura OU largura...
                // E o tamanho for maior que o mínimo permitido...
                // O check (el.offsetHeight > 0) garante que o elemento está visível
                while (el.offsetHeight > 0 && (el.scrollHeight > el.clientHeight || el.scrollWidth > el.clientWidth) && size > localMin) {
                    size -= 0.2; // Reduz 0.2px por passo
                    el.style.fontSize = size + 'px';
                }
            });
        }
        
        // Timeout para garantir renderização do Grid CSS
        setTimeout(() => {
            const scale = {{ $scale }};
            
            // Título: tenta manter próximo de 6pt (ajustado pela escala)
            fitText('.auto-fit', 6 * scale); 
            
            // Ingredientes: tenta manter próximo de 4.5pt (ajustado pela escala)
            fitText('.auto-fit-ingredients', 4.5 * scale);
        }, 100);
    })();
</script>