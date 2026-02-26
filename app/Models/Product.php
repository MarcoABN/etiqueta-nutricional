<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; // Importante para gerar o UUID

class Product extends Model
{
    use HasFactory;

    // 1. Definimos que o ID não é auto-incremento (1, 2, 3...)
    public $incrementing = false;

    // 2. O tipo do ID é string
    protected $keyType = 'string';

    protected $guarded = ['id'];

    // 3. Evento para criar o UUID automaticamente ao salvar
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Se não tiver ID, gera um UUID v4 padrão
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        // Identificação e Controle
        'codprod',
        'barcode',         // EAN/GTIN
        'curve',           // A, B, C
        'ncm',
        'pesoliq',
        'unidade',
        'qtunitcx',
        'import_status',   // Bloqueado, Em Análise, Liberado
        'product_name',
        'product_name_en',
        'imported_by',

        // Porções
        'servings_per_container',
        'serving_weight',
        'serving_size_quantity',
        'serving_size_unit',

        // Macronutrientes Principais
        'calories',
        'total_carb',
        'total_carb_dv',
        'total_sugars',
        'added_sugars',
        'added_sugars_dv',
        'sugar_alcohol',
        'protein',
        'protein_dv',

        // Gorduras
        'total_fat',
        'total_fat_dv',
        'sat_fat',
        'sat_fat_dv',
        'trans_fat',
        'trans_fat_dv',
        'poly_fat',
        'mono_fat',

        // Outros
        'fiber',
        'fiber_dv',
        'sodium',
        'sodium_dv',
        'cholesterol',
        'cholesterol_dv',

        // Micronutrientes
        'vitamin_d',
        'calcium',
        'iron',
        'potassium',
        'vitamin_a',
        'vitamin_c',
        'vitamin_e',
        'vitamin_k',
        'thiamin',
        'riboflavin',
        'niacin',
        'vitamin_b6',
        'folate',
        'vitamin_b12',
        'biotin',
        'pantothenic_acid',
        'phosphorus',
        'iodine',
        'magnesium',
        'zinc',
        'selenium',
        'copper',
        'manganese',
        'chromium',
        'molybdenum',
        'chloride',

        // Textos Legais
        'ingredients',
        'allergens_contains',
        'allergens_may_contain',
        'image_nutritional',

        'ingredients_pt',
        'allergens_contains_pt',
        'allergens_may_contain_pt',
    ];

    /**
     * Definição de valores padrão para atributos do modelo.
     */
    protected $attributes = [
        'import_status' => 'Bloqueado',
        'trans_fat' => '0',
        'trans_fat_dv' => '0',
        'cholesterol' => '0',
        'cholesterol_dv' => '0',
        'serving_size_unit' => 'Unit',
    ];

    public function hasMicronutrients(): bool
    {
        $fields = [
            'vitamin_d',
            'calcium',
            'iron',
            'potassium',
            'vitamin_a',
            'vitamin_c',
            'vitamin_e',
            'vitamin_k',
            'thiamin',
            'riboflavin',
            'niacin',
            'vitamin_b6',
            'folate',
            'vitamin_b12',
            'biotin',
            'pantothenic_acid',
            'phosphorus',
            'iodine',
            'magnesium',
            'zinc',
            'selenium',
            'copper',
            'manganese',
            'chromium',
            'molybdenum',
            'chloride'
        ];

        foreach ($fields as $field) {
            // Pega o valor do campo
            $val = $this->{$field};

            // Converte para float para garantir a comparação numérica
            // Se for null, "0", "0.00" ou vazio, retorna false na verificação > 0
            if (floatval($val) > 0) {
                return true; // Encontrou pelo menos um!
            }
        }

        return false; // Não encontrou nenhum
    }
}