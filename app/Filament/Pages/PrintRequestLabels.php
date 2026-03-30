<?php

namespace App\Filament\Pages;

use App\Models\Request;
use App\Models\RequestItem;
use App\Models\LabelSetting;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Tabs;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Actions\Action as PageAction;
use Filament\Notifications\Notification;

class PrintRequestLabels extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationLabel = 'Etiquetas por Solicitação';
    protected static ?string $title = 'Impressão em Lote (Solicitação)';
    protected static string $view = 'filament.pages.print-request-labels';

    protected static ?string $navigationGroup = 'Etiquetas';
    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    // --- AÇÃO NO CABEÇALHO: CALIBRAÇÃO UNIFICADA ---
    protected function getHeaderActions(): array
    {
        return [
            PageAction::make('calibrate')
                ->label('Calibração de Impressoras')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->slideOver()
                ->modalHeading('Ajuste Global de Margens')
                ->modalDescription('As alterações feitas aqui refletem em todo o sistema.')
                ->fillForm(function () {
                    // Carrega do banco as configurações atuais de ambos os papéis
                    $std = LabelSetting::firstOrCreate(['layout' => 'standard'], ['padding_top' => 2, 'padding_left' => 2, 'padding_right' => 2, 'padding_bottom' => 2, 'gap_width' => 6, 'font_scale' => 100]);
                    $tab = LabelSetting::firstOrCreate(['layout' => 'tabular'], ['padding_top' => 2, 'padding_left' => 2, 'padding_right' => 2, 'padding_bottom' => 2, 'gap_width' => 6, 'font_scale' => 100]);

                    return [
                        'std_padding_top' => $std->padding_top,
                        'std_padding_left' => $std->padding_left,
                        'std_padding_right' => $std->padding_right,
                        'std_padding_bottom' => $std->padding_bottom,
                        'std_gap_width' => $std->gap_width,
                        'std_font_scale' => $std->font_scale,
                        'tab_padding_top' => $tab->padding_top,
                        'tab_padding_left' => $tab->padding_left,
                        'tab_padding_right' => $tab->padding_right,
                        'tab_padding_bottom' => $tab->padding_bottom,
                        'tab_gap_width' => $tab->gap_width,
                        'tab_font_scale' => $tab->font_scale,
                    ];
                })
                ->form([
                    Tabs::make('Layouts')->tabs([
                        Tabs\Tab::make('Padrão Vertical (Caixa)')->schema([
                            Grid::make(2)->schema([
                                TextInput::make('std_padding_top')->label('Margem Topo (mm)')->numeric()->step(0.1),
                                TextInput::make('std_padding_left')->label('Margem Esq. (mm)')->numeric()->step(0.1),
                                TextInput::make('std_padding_right')->label('Margem Dir. (mm)')->numeric()->step(0.1),
                                TextInput::make('std_padding_bottom')->label('Margem Inf. (mm)')->numeric()->step(0.1),
                                TextInput::make('std_gap_width')->label('Gap Central (mm)')->numeric()->step(0.1),
                                TextInput::make('std_font_scale')->label('Escala Fonte (%)')->numeric(),
                            ])
                        ]),
                        Tabs\Tab::make('Tabular Horizontal (Produto)')->schema([
                            Grid::make(2)->schema([
                                TextInput::make('tab_padding_top')->label('Margem Topo (mm)')->numeric()->step(0.1),
                                TextInput::make('tab_padding_left')->label('Margem Esq. (mm)')->numeric()->step(0.1),
                                TextInput::make('tab_padding_right')->label('Margem Dir. (mm)')->numeric()->step(0.1),
                                TextInput::make('tab_padding_bottom')->label('Margem Inf. (mm)')->numeric()->step(0.1),
                                TextInput::make('tab_gap_width')->label('Gap Central (mm)')->numeric()->step(0.1),
                                TextInput::make('tab_font_scale')->label('Escala Fonte (%)')->numeric(),
                            ])
                        ]),
                    ])
                ])
                ->action(function (array $data) {
                    LabelSetting::updateOrCreate(['layout' => 'standard'], [
                        'padding_top' => $data['std_padding_top'],
                        'padding_left' => $data['std_padding_left'],
                        'padding_right' => $data['std_padding_right'],
                        'padding_bottom' => $data['std_padding_bottom'],
                        'gap_width' => $data['std_gap_width'],
                        'font_scale' => $data['std_font_scale'],
                    ]);
                    LabelSetting::updateOrCreate(['layout' => 'tabular'], [
                        'padding_top' => $data['tab_padding_top'],
                        'padding_left' => $data['tab_padding_left'],
                        'padding_right' => $data['tab_padding_right'],
                        'padding_bottom' => $data['tab_padding_bottom'],
                        'gap_width' => $data['tab_gap_width'],
                        'font_scale' => $data['tab_font_scale'],
                    ]);
                    Notification::make()->title('Calibrações globais atualizadas')->success()->send();
                })
        ];
    }

    // --- FORMULÁRIO SUPERIOR: SELEÇÃO DA SOLICITAÇÃO ---
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filtro de Solicitação')
                    ->schema([
                        Grid::make(12)->schema([
                            Select::make('request_id')
                                ->label('Pesquisar Solicitação')
                                ->placeholder('Selecione pelo Nº do Pedido')
                                // CORREÇÃO 1: Mostra apenas solicitações em aberto
                                ->options(Request::where('status', 'aberto')->orderByDesc('created_at')->limit(100)->pluck('display_id', 'id'))
                                ->searchable()
                                ->live()
                                // CORREÇÃO 2: Limpa o cache da tabela e força o recarregamento ao selecionar
                                ->afterStateUpdated(function ($livewire) {
                                    if (method_exists($livewire, 'resetTable')) {
                                        $livewire->resetTable();
                                    }
                                })
                                ->columnSpan(['default' => 12, 'lg' => 3]),

                            Placeholder::make('req_status')
                                ->label('Status')
                                ->content(fn(Get $get) => ucfirst(Request::find($get('request_id'))?->status ?? '-'))
                                ->columnSpan(['default' => 6, 'lg' => 2]),

                            Placeholder::make('req_date')
                                ->label('Data de Criação')
                                ->content(fn(Get $get) => Request::find($get('request_id'))?->created_at?->format('d/m/Y H:i') ?? '-')
                                ->columnSpan(['default' => 6, 'lg' => 2]),

                            Placeholder::make('req_obs')
                                ->label('Observação')
                                ->content(fn(Get $get) => Request::find($get('request_id'))?->observation ?? '-')
                                ->columnSpan(['default' => 12, 'lg' => 5]),
                        ])
                    ])
            ])
            ->statePath('data');
    }

    // --- TABELA INFERIOR: LISTAGEM E BOTÕES DE IMPRESSÃO ---
    public function table(Table $table): Table
    {
        $reqId = $this->data['request_id'] ?? null;

        return $table
            ->query(
                RequestItem::query()
                    ->with('product')
                    ->when($reqId, function ($query, $id) {
                        return $query->where('request_id', $id);
                    }, function ($query) {
                        return $query->whereRaw('1 = 0');
                    })
                    ->whereNotNull('product_id')
            )
            ->columns([
                TextColumn::make('product_name')
                    ->label('Produto')
                    ->weight('bold')
                    ->wrap()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qtd Cx')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('product.qtunitcx')
                    ->label('UN por Caixa')
                    ->numeric()
                    ->alignCenter()
                    ->default('N/D'),

                TextColumn::make('sugerida')
                    ->label('Qtd Sugerida')
                    ->state(function (RequestItem $record) {
                        $qtunitcx = (int) ($record->product->qtunitcx ?? 0);

                        if ($qtunitcx === 0) return 0;

                        $adjusted_qtunitcx = ($qtunitcx % 2 !== 0) ? $qtunitcx + 1 : $qtunitcx;

                        $totalSugerido = ($record->quantity * $adjusted_qtunitcx) / 2;

                        return ceil($totalSugerido);
                    })
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->tooltip('Calculado: (Qtd Caixas * Unidades) / 2. Ímpares são arredondados para cima para fechar o par.'),
            ])
            ->actions([
                TableAction::make('print_tabular')
                    ->label('Interna (Produto)')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->action(function (RequestItem $record) {
                        $url = route('print.label', ['product' => $record->product_id]) . '?layout=tabular';
                        $this->dispatch('print-label-event', url: $url);
                    }),

                TableAction::make('print_standard')
                    ->label('Externa (Caixa)')
                    ->icon('heroicon-o-document')
                    ->color('primary')
                    ->action(function (RequestItem $record) {
                        $url = route('print.label', ['product' => $record->product_id]) . '?layout=standard';
                        $this->dispatch('print-label-event', url: $url);
                    }),
            ])
            ->emptyStateHeading('Selecione uma Solicitação')
            ->emptyStateDescription('Utilize o campo acima para carregar os produtos.')
            ->emptyStateIcon('heroicon-o-arrow-up')
            ->paginated(false);
    }
}
