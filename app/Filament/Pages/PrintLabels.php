<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\LabelSetting;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PrintLabels extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'Imprimir Etiquetas';
    protected static ?string $title = 'Central de Impressão FDA';
    protected static string $view = 'filament.pages.print-labels';

    // Propriedades do Formulário Principal
    public ?string $search_code = '';
    public int $quantity = 1;
    public string $labelLayout = 'standard';
    
    // Propriedade para armazenar os ajustes de calibração
    // Mapeamos os inputs para settingsData.padding_top, etc.
    public array $settingsData = [];

    public ?Product $product = null;

    public function mount(): void
    {
        // Ao iniciar, carrega as configurações do layout padrão
        $this->loadSettingsForLayout('standard');
    }

    /**
     * Hook do Livewire: Disparado automaticamente quando $labelLayout muda.
     * Isso troca os valores dos inputs de calibração instantaneamente.
     */
    public function updatedLabelLayout($value)
    {
        $this->loadSettingsForLayout($value);
        
        Notification::make()
            ->title('Calibração carregada: ' . ucfirst($value))
            ->info()
            ->duration(2000)
            ->send();
    }

    /**
     * Carrega as configurações do banco para a memória do componente ($settingsData)
     */
    public function loadSettingsForLayout($layout)
    {
        // Busca ou cria com valores default seguros
        $settings = LabelSetting::firstOrCreate(
            ['layout' => $layout],
            [
                'padding_top' => 2.0, 
                'padding_left' => 2.0,
                'padding_right' => 2.0, 
                'padding_bottom' => 2.0,
                'gap_width' => 6.0, 
                'font_scale' => 100
            ]
        );

        $this->settingsData = $settings->toArray();
    }

    /**
     * Salva as alterações feitas na seção de calibração para o layout atual
     */
    public function saveSettings()
    {
        LabelSetting::updateOrCreate(
            ['layout' => $this->labelLayout], // Chave de busca (o layout atual selecionado)
            $this->settingsData // Os dados do form
        );

        Notification::make()
            ->title('Ajustes salvos para layout: ' . ucfirst($this->labelLayout))
            ->success()
            ->send();
            
        // Força renderização para atualizar preview se necessário
        $this->dispatch('settings-saved'); 
    }

    /**
     * Passa os dados para a View Blade (incluindo o Preview)
     */
    protected function getViewData(): array
    {
        // Criamos uma instância de LabelSetting com os dados da memória ($this->settingsData).
        // Assim, o preview reflete o que está nos inputs (se usarmos wire:model.live), 
        // ou o que está no banco carregado.
        $previewSettings = new LabelSetting($this->settingsData);

        return [
            'settings' => $previewSettings,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // --- SEÇÃO 1: BUSCA E CONTROLE ---
                Grid::make(5)->schema([
                    TextInput::make('search_code')
                        ->label('Pesquisar Produto')
                        ->placeholder('Cód. WinThor ou Barras')
                        ->autofocus()
                        ->required()
                        ->suffixAction(
                            Action::make('search')
                                ->icon('heroicon-m-magnifying-glass')
                                ->action(fn() => $this->searchProduct())
                        )
                        ->extraInputAttributes(['wire:keydown.enter' => 'searchProduct'])
                        ->columnSpan(2),

                    Select::make('labelLayout')
                        ->label('Layout / Papel')
                        ->options([
                            'standard' => 'Padrão Vertical (100x80mm)',
                            'tabular' => 'Tabular Horizontal (80x50mm - Dupla)',
                        ])
                        ->default('standard')
                        ->selectablePlaceholder(false)
                        ->live() // Essencial para disparar updatedLabelLayout
                        ->required()
                        ->columnSpan(2),

                    TextInput::make('quantity')
                        ->label('Qtd.')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(1000)
                        ->required()
                        ->columnSpan(1),
                ]),

                // --- SEÇÃO 2: CALIBRAÇÃO INTEGRADA ---
                Section::make('Calibração da Impressora')
                    ->description(fn() => 'Ajustes finos salvos especificamente para: ' . ucfirst($this->labelLayout))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed() // Mantém fechado para não poluir
                    ->compact()
                    ->schema([
                        Grid::make(5)->schema([
                            TextInput::make('settingsData.padding_top')
                                ->label('Margem Topo (mm)')
                                ->numeric()->step(0.1),
                            
                            TextInput::make('settingsData.padding_left')
                                ->label('Margem Esq. (mm)')
                                ->numeric()->step(0.1),

                            TextInput::make('settingsData.gap_width')
                                ->label('Gap Central (mm)')
                                ->helperText('Espaço do corte')
                                ->numeric()->step(0.1),

                            TextInput::make('settingsData.font_scale')
                                ->label('Escala Fonte (%)')
                                ->numeric(),

                            // Botão de Salvar dentro da Grid
                            \Filament\Forms\Components\Actions::make([
                                Action::make('save_config')
                                    ->label('Salvar Ajuste')
                                    ->action(fn() => $this->saveSettings())
                                    ->color('primary')
                                    ->icon('heroicon-m-check'),
                            ])->alignCenter()->verticalAlignment('end'), // Alinha com os inputs
                        ]),
                    ]),

                // --- SEÇÃO 3: STATUS VISUAL ---
                Placeholder::make('status_display')
                    ->label('Produto Selecionado')
                    ->hidden(fn() => !$this->product)
                    ->content(function () {
                        if (!$this->product) return '-';
                        $status = $this->product->import_status ?? 'Indefinido';
                        $color = match ($status) {
                            'Liberado' => 'text-green-600',
                            'Processado (IA)' => 'text-blue-500',
                            'Bloqueado' => 'text-red-600',
                            default => 'text-gray-500',
                        };
                        return new HtmlString("<div class='flex items-center gap-2'><span class='text-lg font-black {$color}'>{$status}</span> <span class='text-gray-600'>| {$this->product->product_name}</span></div>");
                    })
                    ->columnSpanFull(),
            ]);
    }

    public function searchProduct()
    {
        $this->validate(['search_code' => 'required']);
        $search = trim($this->search_code);
        $maxInt = 2147483647;

        $query = Product::query();

        if (is_numeric($search) && $search > $maxInt) {
            $query->where('barcode', $search);
        } else {
            $query->where('codprod', $search)
                ->orWhere('codprod', ltrim($search, '0'))
                ->orWhere('barcode', $search);
        }

        $found = $query->first();

        if ($found) {
            $this->product = $found;
            Notification::make()->title('Produto carregado')->success()->send();
        } else {
            Notification::make()->title('Produto não encontrado')->danger()->send();
        }
    }
}