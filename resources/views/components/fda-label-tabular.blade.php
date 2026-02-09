@props(['product', 'settings'])

@if(!$product)
    <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: #fee2e2; color: #991b1b; font-weight: bold; border: 2px dashed #f87171;">
        ERRO: Produto não carregado.
    </div>
@else

@php
    $gapWidth = $settings->gap_width ?? 4; 
    $scale    = ($settings->font_scale ?? 100) / 100;
    
    // Dimensões Tabular (78x48mm visual)
    $targetVisualWidth = 78.0; 
    $maxPhysicalWidth = 50 - ($gapWidth / 2);
    $targetVisualHeight = min(48.0, $maxPhysicalWidth);
    $innerPad = 1.5; 

    // Dados
    $microsText = collect([
        ['Vitamin D', $product->vitamin_d, 'mcg'],
        ['Calcium', $product->calcium, 'mg'],
        ['Iron', $product->iron, 'mg'],
        ['Potassium', $product->potassium, 'mg'],
    ])->filter(fn($m) => floatval($m[1]) > 0)
      ->map(fn($m) => "{$m[0]} {$m[1]}{$m[2]}")->implode(' • ');

    $col2_data = [
        ['Total Fat', ($product->total_fat ?? '0').'g', $product->total_fat_dv, true],
        ['Saturated Fat', ($product->sat_fat ?? '0').'g', $product->sat_fat_dv, false, true],
        ['Trans Fat', ($product->trans_fat ?? '0').'g', '', false, true],
        ['Cholesterol', ($product->cholesterol ?? '0').'mg', $product->cholesterol_dv, true],
        ['Sodium', ($product->sodium ?? '0').'mg', $product->sodium_dv, true],
    ];

    $col3_data = [
        ['Total Carb.', ($product->total_carb ?? '0').'g', $product->total_carb_dv, true],
        ['Dietary Fiber', ($product->fiber ?? '0').'g', $product->fiber_dv, false, true],
        ['Total Sugars', ($product->total_sugars ?? '0').'g', '', false, true],
        ['Incl. Added Sugars', ($product->added_sugars ?? '0').'g', $product->added_sugars_dv, false, true],
        ['Protein', ($product->protein ?? '0').'g', $product->protein_dv, true],
    ];

    $drawLabelContent = function() use ($product, $scale, $microsText, $col2_data, $col3_data, $innerPad, $targetVisualWidth, $targetVisualHeight) {
        ?>
        <div style="
            width: <?php echo $targetVisualWidth; ?>mm; 
            height: <?php echo $targetVisualHeight; ?>mm;
            padding: <?php echo $innerPad; ?>mm;
            background: white; 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        ">
            <div style="border: 2px solid black; width: 100%; height: 100%; display: flex; flex-direction: column;">

                <div style="height: 50%; display: flex; border-bottom: 1px solid black;">
                    <div style="width: 30%; display: flex; flex-direction: column; justify-content: space-between;">
                        <div style="padding: 2px 0 0 3px;">
                            <div style="font-weight: 900; font-size: <?php echo 11 * $scale; ?>pt; line-height: 0.9; letter-spacing: -0.5px;">Nutrition Facts</div>
                            <div style="border-bottom: 1px solid black; margin-top: 2px; width: 95%;"></div>
                        </div>
                        <div style="padding: 0 0 0 3px; display: flex; flex-direction: column; justify-content: center; flex-grow: 1;">
                            <div style="font-size: <?php echo 5 * $scale; ?>pt; line-height: 1.1; margin-bottom: 2px;">
                                <?php echo $product->servings_per_container ?? 'Varied'; ?> servings per container
                            </div>
                            <div style="font-size: <?php echo 5.5 * $scale; ?>pt; line-height: 1.1; font-weight: 900; color: black;">
                                Serving size<br>
                                <?php echo $product->serving_size_quantity ?? '0'; ?><?php echo $product->serving_size_unit; ?> (<?php echo $product->serving_weight; ?>)
                            </div>
                        </div>
                        <div style="border-top: 1px solid black; padding: 2px 2px 2px 3px; margin-top: 2px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                                <div style="line-height: 1.1;">
                                    <div style="font-size: <?php echo 6 * $scale; ?>pt; font-weight: 900;">Calories</div>
                                    <div style="font-size: <?php echo 5 * $scale; ?>pt; font-weight: bold;">per serving</div>
                                </div>
                                <div style="font-size: <?php echo 14 * $scale; ?>pt; font-weight: 900; line-height: 0.9;">
                                    <?php echo $product->calories ?? '0'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="width: 35%; display: flex; flex-direction: column; padding-left: 4px;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid black; padding: 1px 2px 1px 0; font-size: <?php echo 5 * $scale; ?>pt; font-weight: bold;">
                            <span>Amount/serving</span>
                            <span>% DV</span>
                        </div>
                        <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: space-evenly;">
                            <?php foreach($col2_data as $item): ?>
                                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid black; font-size: <?php echo 5.5 * $scale; ?>pt; line-height: 1.1; padding-right: 2px;">
                                    <span style="white-space: nowrap; <?php echo isset($item[4]) ? 'padding-left: 4px;' : ''; ?>">
                                        <?php if($item[3]): ?><strong><?php echo $item[0]; ?></strong><?php else: echo $item[0]; endif; ?> <?php echo $item[1]; ?>
                                    </span>
                                    <?php if($item[2] !== ''): ?><strong style="font-size: <?php echo 5.5 * $scale; ?>pt;"><?php echo $item[2]; ?>%</strong><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="width: 35%; display: flex; flex-direction: column; padding-left: 6px;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid black; padding: 1px 2px 1px 0; font-size: <?php echo 5 * $scale; ?>pt; font-weight: bold;">
                            <span>Amount/serving</span>
                            <span>% DV</span>
                        </div>
                        <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: space-evenly;">
                            <?php foreach($col3_data as $item): ?>
                                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid black; font-size: <?php echo 5.5 * $scale; ?>pt; line-height: 1.1; padding-right: 2px;">
                                    <span style="white-space: nowrap; <?php echo isset($item[4]) ? 'padding-left: 4px;' : ''; ?>">
                                        <?php if($item[3]): ?><strong><?php echo $item[0]; ?></strong><?php else: echo $item[0]; endif; ?> <?php echo $item[1]; ?>
                                    </span>
                                    <?php if($item[2] !== ''): ?><strong style="font-size: <?php echo 5.5 * $scale; ?>pt;"><?php echo $item[2]; ?>%</strong><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div style="height: 50%; padding: 1px 3px 2px 3px; display: flex; flex-direction: column; justify-content: space-between;">
                    
                    <div style="flex-grow: 1; display: flex; flex-direction: column; min-height: 0;">
                        <div style="font-size: <?php echo 5 * $scale; ?>pt; line-height: 1.1; margin-bottom: 2px;">
                            <?php if($microsText): ?>
                                <div style="margin-bottom: 1px; border-bottom: 1px solid black; padding-bottom: 1px;"><?php echo $microsText; ?></div>
                            <?php endif; ?>
                            
                            <div style="font-weight: bold; text-transform: uppercase; margin-bottom: 2px;">
                                <?php echo $product->product_name_en ?? $product->product_name; ?>
                            </div>
                        </div>

                        <div style="
                            flex-grow: 1;
                            overflow: hidden; 
                            display: -webkit-box; 
                            -webkit-line-clamp: 5;
                            -webkit-box-orient: vertical; 
                            text-overflow: ellipsis;
                            font-size: <?php echo 5 * $scale; ?>pt; 
                            line-height: 1.1;
                            margin-bottom: 2px;
                            min-height: 0;
                        ">
                            <strong>INGREDIENTS:</strong> <?php echo $product->ingredients; ?>
                        </div>
                        
                        <div style="font-size: <?php echo 5 * $scale; ?>pt; line-height: 1.1; flex-shrink: 0;">
                            <?php if($product->allergens_contains): ?> 
                                <div><strong style="font-weight: 800;">CONTAINS:</strong> <?php echo $product->allergens_contains; ?></div> 
                            <?php endif; ?>
                            <?php if($product->allergens_may_contain): ?> 
                                <div><strong style="font-weight: 800;">MAY CONTAIN:</strong> <?php echo $product->allergens_may_contain; ?></div> 
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="font-size: <?php echo 4.5 * $scale; ?>pt; line-height: 1.0; border-top: 1px solid black; padding-top: 1px; display: flex; justify-content: space-between; align-items: flex-end; flex-shrink: 0;">
                        <div style="max-width: 65%;">
                            <?php if(filled($product->imported_by)): ?> IMP: <?php echo nl2br(e($product->imported_by)); ?> <?php else: ?> IMPORTED BY: GO MINAS DISTRIBUTION LLC<br>MARGATE, FL 33063 <?php endif; ?>
                        </div>
                        <div style="font-weight: bold; text-align: right;">Product of <?php echo $product->origin_country ?? 'Brazil'; ?></div>
                    </div>
                </div>

            </div>
        </div>
        <?php
    };
@endphp

<div style="position: relative; width: 100mm; height: 80mm; background: white; overflow: hidden;">
    
    {{-- ETIQUETA 1 --}}
    <div style="
        position: absolute; 
        top: 0; left: 0; 
        width: <?php echo 50 - ($gapWidth / 2); ?>mm; height: 80mm; 
        display: flex; align-items: center; justify-content: center;
    ">
        <div style="transform: rotate(90deg);">{{ $drawLabelContent() }}</div>
    </div>

    {{-- GAP --}}
    <div style="
        position: absolute;
        left: <?php echo 50 - ($gapWidth / 2); ?>mm;
        width: <?php echo $gapWidth; ?>mm;
        height: 80mm;
    "></div>

    {{-- ETIQUETA 2 --}}
    <div style="
        position: absolute; 
        top: 0; left: <?php echo 50 + ($gapWidth / 2); ?>mm; 
        width: <?php echo 50 - ($gapWidth / 2); ?>mm; height: 80mm; 
        display: flex; align-items: center; justify-content: center;
    ">
        <div style="transform: rotate(90deg);">{{ $drawLabelContent() }}</div>
    </div>

</div>
@endif