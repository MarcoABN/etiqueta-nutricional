<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\LabelSetting;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class PrintLabels extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'Imprimir Etiquetas';
    protected static ?string $title = 'Central de Impressão FDA';
    protected static string $view = 'filament.pages.print-labels';

    protected static ?string $navigationGroup = 'Etiquetas';
    protected static ?int $navigationSort = 1;

    public ?string $search_code = '';
    public string $labelLayout = 'standard';
    public string $unit = 'oz';
    
    public array $settingsData = [];

    public ?Product $product = null;

    public function mount(): void
    {
        $this->loadSettingsForLayout('standard');
    }

    public function updatedLabelLayout($value)
    {
        $this->loadSettingsForLayout($value);
        
        Notification::make()
            ->title('Calibração carregada: ' . ucfirst($value))
            ->info()
            ->duration(2000)
            ->send();
    }

    public function loadSettingsForLayout($layout)
    {
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

    public function saveSettings()
    {
        LabelSetting::updateOrCreate(
            ['layout' => $this->labelLayout],
            $this->settingsData 
        );

        Notification::make()
            ->title('Ajustes salvos para layout: ' . ucfirst($this->labelLayout))
            ->success()
            ->send();
            
        $this->dispatch('settings-saved'); 
    }

    protected function getViewData(): array
    {
        $previewSettings = new LabelSetting($this->settingsData);

        return [
            'settings' => $previewSettings,
            'selectedUnit' => $this->unit,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(12)->schema([
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
                        ->columnSpan(4),

                    Select::make('labelLayout')
                        ->label('Layout / Papel')
                        ->options([
                            'standard' => 'Padrão Vertical (100x80mm)',
                            'tabular' => 'Tabular Horizontal (80x50mm - Dupla)',
                        ])
                        ->default('standard')
                        ->selectablePlaceholder(false)
                        ->live() 
                        ->required()
                        ->columnSpan(4),

                    Select::make('unit')
                        ->label('Unidade')
                        ->options([
                            'oz' => 'Onça (oz)',
                            'g' => 'Grama (g)',
                        ])
                        ->default('oz')
                        ->selectablePlaceholder(false)
                        ->live()
                        ->required()
                        ->columnSpan(2),

                    \Filament\Forms\Components\Actions::make([
                        Action::make('calibrate')
                            ->label('Calibração')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->color('gray')
                            ->slideOver()
                            ->modalHeading(fn() => 'Calibração: ' . ucfirst($this->labelLayout))
                            ->modalDescription('Ajustes finos salvos especificamente para o papel e impressora atuais.')
                            ->modalSubmitActionLabel('Salvar Ajustes')
                            ->fillForm(fn() => $this->settingsData)
                            ->form([
                                Grid::make(2)->schema([
                                    TextInput::make('padding_top')->label('Margem Topo (mm)')->numeric()->step(0.1),
                                    TextInput::make('padding_left')->label('Margem Esq. (mm)')->numeric()->step(0.1),
                                    TextInput::make('padding_right')->label('Margem Dir. (mm)')->numeric()->step(0.1),
                                    TextInput::make('padding_bottom')->label('Margem Inf. (mm)')->numeric()->step(0.1),
                                    TextInput::make('gap_width')->label('Gap Central (mm)')->helperText('Espaço do corte')->numeric()->step(0.1),
                                    TextInput::make('font_scale')->label('Escala Fonte (%)')->numeric(),
                                ])
                            ])
                            ->action(function (array $data) {
                                $this->settingsData = $data;
                                $this->saveSettings();
                            }),
                    ])
                    ->columnSpan(2)
                    ->verticalAlignment('end'),
                ]),
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