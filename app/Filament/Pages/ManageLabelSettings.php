<?php

namespace App\Filament\Pages;

use App\Models\LabelSetting;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class ManageLabelSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Config. Impressão';
    protected static ?string $title = 'Calibração da Etiqueta Zebra';
    protected static string $view = 'filament.pages.manage-label-settings';

    // Propriedade para segurar os dados do formulário
    public ?array $data = [];

    public function mount(): void
    {
        // Busca a config ou cria a padrão
        $settings = LabelSetting::firstOrCreate([
            'id' => 1
        ], [
            'padding_top' => 2.0,
            'padding_left' => 2.0,
            'gap_width' => 6.0,
            'font_scale' => 100,
        ]);

        $this->form->fill($settings->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Margens e Centralização')
                    ->description('Ajuste para centralizar a impressão no papel (Unidade: Milímetros)')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('padding_top')->label('Margem Superior (mm)')->numeric()->step(0.1)->suffix('mm'),
                                TextInput::make('padding_bottom')->label('Margem Inferior (mm)')->numeric()->step(0.1)->suffix('mm'),
                                TextInput::make('padding_left')->label('Margem Esquerda (mm)')->numeric()->step(0.1)->suffix('mm'),
                                TextInput::make('padding_right')->label('Margem Direita (mm)')->numeric()->step(0.1)->suffix('mm'),
                            ]),
                    ]),

                Section::make('Corte e Layout')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('gap_width')
                                    ->label('Espaço Central (Gap/Corte)')
                                    ->helperText('Distância vazia no meio da etiqueta para o corte físico.')
                                    ->numeric()->step(0.1)->suffix('mm')
                                    ->required(),

                                TextInput::make('font_scale')
                                    ->label('Escala da Fonte (%)')
                                    ->helperText('100% é o tamanho padrão. Reduza para 90% se estiver cortando texto.')
                                    ->numeric()->minValue(50)->maxValue(150)->suffix('%')
                                    ->required(),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $settings = LabelSetting::find(1);
        $settings->update($this->form->getState());

        Notification::make()->title('Configurações salvas com sucesso!')->success()->send();
    }
    
    // Botão de salvar customizado
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar Alterações')
                ->submit('save'),
        ];
    }
}